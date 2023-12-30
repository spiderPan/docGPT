<?php

use Pan\DocGpt\DocGPT;
use Pan\DocGpt\Logger\FileLogger;
use Pan\DocGpt\OpenAI\APIClient;
use Pan\DocGpt\VectorDB\PgvectorClient;

// PDO connection to the vector database
$pdo            = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
$pgvectorClient = new PgvectorClient($pdo);

// OpenAI API client
$openaiClient = new APIClient('openai_api_key');
$docGpt       = new DocGPT($openaiClient, $pgvectorClient);

// (Optional) set a FileLogger
$logger = new FileLogger('/var/www/html/docGPT/logs/');
$docGpt->setLogger($logger);

// Learn a document
$docGpt->learn('This is a test document to learn');

// Chat with a document
$docGpt->chat('This is a test document', 'test');
