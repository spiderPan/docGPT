<?php

use Pan\DocGpt\DocGPT;
use Pan\DocGpt\Logger\FileLogger;
use Pan\DocGpt\Model\Step;
use Pan\DocGpt\Model\Steps;
use Pan\DocGpt\OpenAI\APIClient;
use Pan\DocGpt\VectorDB\PgvectorClient;

// Example 1: use DocGPT to chat with a document

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


// Example 2: use multiple steps to chat with a document

// Construct a steps
$steps = new Steps();
$steps->addStep(new Step('Prompt for step 1'));
$steps->addStep(new Step('Prompt for step 2, but will use the previous response as new context'));
$steps->addStep(new Step('Prompt for step 3', ['validate the response', 'exclude the keywords']));

$responses = $docGpt->multiStepsChat($steps, 'test');

foreach ($responses as $response) {
    echo $response . PHP_EOL;
}
