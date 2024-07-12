<?php

use Pan\DocGpt\DocGPT;
use Pan\DocGpt\Logger\FileLogger;
use Pan\DocGpt\Model\Step;
use Pan\DocGpt\Model\Steps;
use Pan\DocGpt\OpenAI\APIClient;
use Pan\DocGpt\VectorDB\PgvectorClient;

// Initialize the $docGpt object
// PDO connection to the vector database (you need to replace 'docGPT', 'doc_gpt_password' with your actual database username and password)
$pdo            = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
$pgvectorClient = new PgvectorClient($pdo);

// OpenAI API client (you need to replace 'openai_api_key' with your actual OpenAI API key)
$openaiClient = new APIClient('openai_api_key');
$docGpt       = new DocGPT($openaiClient, $pgvectorClient);

// (Optional) set a FileLogger
$logger = new FileLogger('/var/www/html/docGPT/logs/');
$docGpt->setLogger($logger);


// Example 1: use DocGPT to chat with a document

// Learn a document
$docGpt->learn('Mary has two dogs. She loves her dogs very much.');

// Ask a question about the document
$answer = $docGpt->chat('How many dogs does Mary have?');
//expected output: two unless the model is not able to understand the context


// Example 2: use multiple steps to chat with a document
// Learn a document from a file
$docGpt->learn(file_get_contents('test.txt'));
// Construct a steps that each step will take the document as well as previous response as the new context
$steps = new Steps();
$steps->addStep(new Step('Prompt for step 1'));
$steps->addStep(new Step('Prompt for step 2, but will use the previous response as new context'));
// Specify a list of keywords to exclude from the response, if the response contains any of these keywords, it will be considered invalid.
// System will re-try 3 times to get a valid response otherwise it will log the error.
$steps->addStep(new Step('Prompt for step 3', ['exclude_the_keywords','exclude_the_keywords2']));

// Chat with the document using the steps
$responses = $docGpt->multiStepsChat($steps, 'test');

foreach ($responses as $response) {
    echo $response . PHP_EOL;
}
