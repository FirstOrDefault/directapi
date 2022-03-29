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
     * @return array The parsed result data
     */
    public function get_api(string $command, ?array $params = null, ?string $prefix = "/CMD_API_") {
        return $this->process_request('GET', "$prefix$command", $params);
    }

    /**
     * Make a post request to the DirectAdmin API
     * 
     * Adds the DirectAdmin API prefix to an internal POST request.
     * 
     * @access public
     * @param string $command The DirectAdmin API command to execute
     * @param array $data The data to transmit with the DirectAdmin API request
     * *MOD* ?array $params removed: don't mix (POST) data with (GET) query params!
     * @param ?string $prefix The prefix to use with the DirectAdmin API request, allows for accessing plugin commands/API's
     * @return array The parsed result data
     */
    public function post_api(string $command, array $data, ?string $prefix = "/CMD_API_") {
        return $this->process_request('POST', "$prefix$command", $data);
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

        if (str_starts_with($input, '{'))
            return json_decode($input);

        if (str_contains($input, "list[]")) 
            return $this->decode_list($input);
        
        return $this->decode_array($input);
    }

    /**
     * Sends a GET or POST request to the DirectAdmin API
     * *MOD*: remove code duplication; replace post() and get()
     * 
     * Uses destination and parameters to create a request to the specified server and returns the parsed result
     * 
     * @access private
     * @param string $method Either POST or GET
     * @param string $destination The page the request should go to
     * @param ?array $parameters The data or query parameters to send with the request
     * @return object The parsed result
     */
    private function process_request(string $method, string $destination, ?array $parameters = null) {
        // assert(in_array($method,['GET','POST']));

        // $prefix ensures that $destination starts with '/'
        $url = $this->protocol . "://" . $this->host . ":" . $this->port . $destination;

        // Prepare http options
        $httpOpt = [
            'header' => [
                'Content-type: application/x-www-form-urlencoded',
                'Authorization: Basic '.base64_encode($this->username() . ":" . $this->password),
                ],
            'method' => $method, // POST/GET
            ];
        // possible enhancement: property additional_headers, method add_headers, use array_merge for httpOpt

        // Prepare parameters
        if ($parameters) {
            $parameters = str_replace("|password|", $this->password, http_build_query($parameters));
            if ($method=='POST') { // content
                $httpOpt['content'] = $parameters;
            }
            else { // GET: querystring
                $url .= '?' . $parameters;
            }
        }

        // Create context and get contents from request
        $result = file_get_contents($url, false, stream_context_create(['http' => $httpOpt]));

        // Return false if request is invalid, else return the parsed result
        // note: result may be an array like ['error'=>99, 'text'=>'error description']
        return ($result===false ? false : $this->parse_result($result));
    }
}

// EOF
