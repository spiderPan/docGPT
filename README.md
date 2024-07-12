# DocGPT PHP Library
TBD

## Installation

To install the `DocGPT` library, you need to have Composer installed on your system. If you don't have Composer
installed, you can download it from [here](https://getcomposer.org/).

Once you have Composer installed, you can install the `DocGPT` library by running the following command in your
terminal:

```bash
composer require spiderpan/doc-gpt
```

## Usage

See [example.php](./example.php) for a working example.

## Development

1. Clone the repo
2. Run `./dev start` to start the docker containers

### Common CLI commands

- `./dev reset` - Rebuild the containers
- `./dev stop` - Stop the app
- `./dev test` - Unit tests are run with PHPUnit
- `./dev test path/to/Test.php` - Run a specific test file
- `./dev shell` - Open a shell in the container
- `./dev lint` - Run PHP CS Fixer
