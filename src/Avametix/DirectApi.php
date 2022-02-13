<?php

namespace Avametix;

/**
 * DirectApi class
 * 
 * @author Roël Couwenberg
 * @package Avametix\DirectApi
 */
class DirectApi 
{
    private string $host;
    private int $port;
    private string $protocol;
    private array $usernames;
    private string $password;

    /**
     * Construct new DirectApi instance
     * 
     * Using a given DirectAdmin installation location, username(s), password and optionally a port create a new DirectApi
     * instance to communicate with the DirectAdmin installation at this location.
     * 
     * @access public
     * @param string $host The hostname for the DirectAdmin installation, e.g. "client.avametix.xyz"
     * @param string $usernames The username or usernames used to log into the installation with, e.g. "admin" or "admin|user"
     * @param string $password The password used to log in to the installation 
     * @param string $protocol The protocol to use with the installation, e.g. "http" or "https"
     * @param int $port The port to use with the installation, e.g. 2222
     */
    public function __construct(string $host, string $usernames, string $password, ?string $protocol = "http", ?int $port = 2222) {
        $this->host = $host;
        $this->port = $port;
        $this->protocol = $protocol;
        $this->usernames = explode("|", $usernames);
        $this->password = $password;
    }
    
    /**
     * Get usernames as string
     * 
     * Parses the known usernames as a string in order to be used in DirectAdmin requests.
     * 
     * @access private
     * @return string
     */
    private function username() {
        return implode("|", $this->usernames);
    }

    /**
     * Change or add second username
     * 
     * These usernames may be used to authenticate DirectAdmin requests.
     * 
     * @access public
     * @param string $username New username
     */
    public function login_as(string $username) {
        $this->usernames[1] = $username;
    }

    /**
     * Removes second username.
     * 
     * @access public
     */
    public function logout() {
        if (count($this->usernames) < 2)
            return;
            
        array_splice($this->usernames, 1, 1);
    }

    /**
     * Make a get request to the DirectAdmin API
     * 
     * Adds the DirectAdmin API prefix to an internal GET request.
     * 
     * @access public
     * @param string $command The DirectAdmin API command to execute
     * @param ?array $params The parameters to transmit with the DirectAdmin API request
     * @param ?string $prefix The prefix to use with the DirectAdmin API request, allows for accessing plugin commands/API's
     * @return array The parsed data
     */
    public function get_api(string $command, ?array $params = null, ?string $prefix = "/CMD_API_") {
        return $this->get("$prefix$command", $params);
    }

    /**
     * Make a post request to the DirectAdmin API
     * 
     * Adds the DirectAdmin API prefix to an internal POST request.
     * 
     * @access public
     * @param string $command The DirectAdmin API command to execute
     * @param array $data The data to transmit with the DirectAdmin API request
     * @param ?array $params The parameters to transmit with the DirectAdmin API request
     * @param ?string $prefix The prefix to use with the DirectAdmin API request, allows for accessing plugin commands/API's
     * @return array The parsed data
     */
    public function post_api(string $command, array $data, ?array $params = null, ?string $prefix = "/CMD_API_") {
        return $this->post("$prefix$command", $data, $params);
    }

    /**
     * Decodes an input as a list
     * 
     * Creates an array of all returned values in input.
     * 
     * @access protected
     * @param string $input The string to parse to a list
     * @return array The parsed list
     */
    protected function decode_list(string $input) {
        $a = explode('&', urldecode($input));
        $values = Array();
        
        $i=0;
        foreach ($a as $v)
        {
            $values[$i++] = substr(strstr($v, '='), 1);
        }
        
        return $values;
    }

    /**
     * Decodes an input as a dictionary
     * 
     * Creates a dictionary of all returned key/value pairs in input.
     * 
     * @access protected
     * @param string $input The string to parse to a dictionary
     * @return array The parsed dictionary
     */
    protected function decode_array(string $input) {
        $a = explode('&', urldecode($input));
        $values = Array();
        
        $i=0;
        foreach ($a as $v)
        {
            $values[substr($v, 0, strpos($v, '='))] = substr(strstr($v, '='), 1);
        }
        
        return $values;
    }

    /**
     * Chooses whether to parse an input as a list or dictionary
     * 
     * Sends input to either decode_list or decode_array
     * 
     * @access private
     * @param string $input The string to choose for
     * @return array The parsed result
     */
    private function parse_result(string $input) {
        if (str_starts_with($input, "<html"))
            return false;

        if (str_contains($input, "list[]")) 
            return $this->decode_list($input);
        
        return $this->decode_array($input);
    }

    /**
     * Sends a get request
     * 
     * Uses destination and data to create a get request to the specified server and returns the parsed result
     * 
     * @access private
     * @param string $destination The page the request should go to
     * @param ?array $params The parameters to send with the request
     * @return object The parsed result
     */
    private function get(string $destination, ?array $params = null) {
        // Combine already set variables to create a valid endpoint
        $url = $this->protocol . "://" . $this->host . ":" . $this->port . $destination;

        // Add parameters to GET request url if params are set
        if (!is_null($params) && isset($params))
            $url .= "?" . http_build_query($params);

        // Set correct headers
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nAuthorization: Basic " . base64_encode($this->username() . ":" . $this->password) . "\r\n",
                'method' => 'GET'
            )
        );

        // Create context and get contents from request
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        // Return false if request is invalid, else return the parsed result
        if ($result === FALSE) {
            return false;
        }

        return $this->parse_result($result);
    }

    /**
     * Sends a post request
     * 
     * Uses destination and data to create a post request to the specified server and returns the parsed result
     * 
     * @access private
     * @param string $destination The page the request should go to
     * @param ?array $data The data to send with the request
     * @param ?array $params The url parameters to send with the request
     * @return object The parsed result
     */
    private function post(string $destination, ?array $data = null, ?array $params = null) {
        // Combine already set variables to create a valid endpoint
        $url = $this->protocol . "://" . $this->host . ":" . $this->port . $destination;

        // Replace data fields where necessary
        if (!is_null($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = str_replace("|password|", $this->password, $value);
            }
        }

        // Add params to the end of the query
        if (!is_null($params))
            $url .= "?" . http_build_query($params);
        
        // Set correct headers
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nAuthorization: Basic " . base64_encode($this->username() . ":" . $this->password) . "\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            )
        );

        // Create context and get contents from request
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        // Return false if request is invalid, else return the parsed result
        if ($result === FALSE) {
            return false;
        }

        return $this->parse_result($result);
    }
}

// EOF