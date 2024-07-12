<?php
// Import necessary classes from the DocGPT library
use Pan\DocGpt\DocGPT;
use Pan\DocGpt\Logger\FileLogger;
use Pan\DocGpt\Model\Step;
use Pan\DocGpt\Model\Steps;
use Pan\DocGpt\OpenAI\APIClient;
use Pan\DocGpt\VectorDB\PgvectorClient;

// Establish a PDO connection to the vector database. Replace placeholders with your actual credentials.
$pdo            = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
$pgvectorClient = new PgvectorClient($pdo);

// Initialize the OpenAI API client with your API key.
$openaiClient = new APIClient('openai_api_key');

// Create the main DocGPT object with the OpenAI client and vector database client.
$docGpt       = new DocGPT($openaiClient, $pgvectorClient);

// Optionally, set up a file-based logger for debugging and monitoring.
$logger = new FileLogger('/var/www/html/docGPT/logs/');
$docGpt->setLogger($logger);


// Example 1: Simple chat interaction with a learned document.
// First, the document is learned by the system.
$docGpt->learn('Mary has two dogs. She loves her dogs very much.');

// Then, ask a question related to the learned content.
$answer = $docGpt->chat('How many dogs does Mary have?');
// Expected output: "two", assuming the model correctly understands the context.


// Example 2: Advanced interaction using multiple steps.
// Learn content from a file for a more complex scenario.
$docGpt->learn(file_get_contents('test.txt'));

// Set up a multi-step conversation:
// Create a Steps object to hold the sequence of conversation steps.
$steps = new Steps();
// Add individual Step objects to the Steps sequence. Each Step should contain a prompt for the chatbot and optionally, conditions for invalid responses.

$steps->addStep(new Step('Prompt for step 1'));
$steps->addStep(new Step('Prompt for step 2, but will use the previous response as new context'));

// Specify exclusion keywords for any step if necessary. These keywords, if found in a response, will mark the response as invalid, prompting a retry.
// If retry attempts are exhausted (currently set to be 3), logger will log a error message and move to the next step.
$steps->addStep(new Step('Prompt for step 3', ['exclude_the_keywords','exclude_the_keywords2']));

$responses = $docGpt->multiStepsChat($steps, 'test');

foreach ($responses as $response) {
    echo $response . PHP_EOL;
}
