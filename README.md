# DirectApi
DirectApi is a wrapper for the DirectAdmin API.

## Installation
Use the package manager [composer](https://getcomposer.org/) to install DirectApi.
```bash
composer require avametix/directapi
```

## Usage

```php
use Avametix\DirectApi;

// Create a new DirectApi instance logged in with user "username" and password "password"
$directapi = new DirectApi(
    'da.domain.io',
    'username',
    'password',
    'https',
    2222
);

// Get user domains
$domains = $directapi->get("SHOW_DOMAINS");

if ($domains === false) {
    die(
        "Something went wrong fetching domains for username,<br/>
        are you using the right password?"
        );
}

foreach ($domain in $domains) {
    echo $domain . "<br/>";
}

// Log-in as user via reseller (directapi is initialised with reseller or admin login)
$directapi->login_as('clientuser');

// ...

$directapi->logout();
```

All DirectAdmin API commands can be found in the [DirectAdmin documentation](https://www.directadmin.com/api.php).

## Contributing
Contributions are primarily made by members of [Avametix](https://avametix.com), however pull requests are welcome. Planning a major change? Please open an issue first and discuss your changes.

## License
[GNU General Public License](https://choosealicense.com/licenses/gpl-3.0/)