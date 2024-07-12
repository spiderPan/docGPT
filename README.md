# DocGPT PHP Library

The DocGPT Project is a PHP-based application designed to index and retrieve information from documents using vector
databases and OpenAI APIs. This project allows users to upload documents, convert their content into vector embeddings,
and store these embeddings in a vector database. Users can then ask questions, and the system will provide relevant
answers by retrieving and interpreting the indexed content using advanced language models.

## Key Features

- Document Indexing: Extracts text from various document formats (PDF, DOCX, TXT) and converts it into vector
  embeddings.
- Vector Storage: Stores the vector embeddings in a vector database for efficient retrieval.
- Question Answering: Uses OpenAI APIs to find the most relevant documents and generate accurate answers based on the
  indexed content.

## Technology Stack

- PHP: Backend scripting language for handling file uploads, API interactions, and database operations.
- OpenAI API: Generates embeddings and answers questions using advanced language models.
- pgvector: A PostgreSQL extension that enables efficient storage and retrieval of vector embeddings.

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
