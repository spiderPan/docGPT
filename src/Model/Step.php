<?php

namespace Pan\DocGpt\Model;

class Step
{

    public string $prompt;
    public array  $exclude_keywords;

    public function __construct(string $prompt, array $exclude_keywords = [])
    {
        $this->prompt           = $prompt;
        $this->exclude_keywords = $exclude_keywords;
    }

    public function setExcludeKeywords(array $exclude_keywords): Step
    {
        $this->exclude_keywords = $exclude_keywords;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getExcludeKeywords(): array
    {
        return $this->exclude_keywords;
    }

    public function isValidResponse(string $response): bool
    {
        foreach ($this->exclude_keywords as $exclude_keyword) {
            if (str_contains($response, $exclude_keyword)) {
                return true;
            }
        }

        return false;
    }
}
