<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => 'openai',
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
            'url' => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'zai' => [
            'driver' => 'groq',
            'key' => env('ZAI_API_KEY'),
            'url' => env('ZAI_URL', 'https://api.z.ai/api/paas/v4'),
            'models' => [
                'text' => [
                    'default' => env('ZAI_TEXT_MODEL', 'glm-5.1'),
                    'smartest' => env('ZAI_TEXT_MODEL', 'glm-5.1'),
                    'cheapest' => env('ZAI_TEXT_MODEL', 'glm-5.1'),
                ],
            ],
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'models' => [
                'text' => [
                    'default' => env('OPENAI_TEXT_MODEL', 'gpt-4o'),
                    'cheapest' => env('OPENAI_TEXT_MODEL_CHEAP', 'gpt-4o-mini'),
                    'smartest' => env('OPENAI_TEXT_MODEL_SMART', 'gpt-4o'),
                ],
            ],
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],
    ],

    'costs' => [
        'currency' => env('AI_COST_CURRENCY', 'USD'),

        'providers' => [
            'openai' => [
                'gpt-4o' => [
                    'input_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_INPUT_PER_MILLION', 2.50),
                    'output_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_OUTPUT_PER_MILLION', 10.00),
                    'cache_write_input_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_CACHE_WRITE_PER_MILLION', 2.50),
                    'cache_read_input_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_CACHE_READ_PER_MILLION', 1.25),
                    'reasoning_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_REASONING_PER_MILLION', 0),
                ],
                'gpt-4o-mini' => [
                    'input_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_MINI_INPUT_PER_MILLION', 0.15),
                    'output_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_MINI_OUTPUT_PER_MILLION', 0.60),
                    'cache_write_input_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_MINI_CACHE_WRITE_PER_MILLION', 0.15),
                    'cache_read_input_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_MINI_CACHE_READ_PER_MILLION', 0.075),
                    'reasoning_per_million' => (float) env('AI_COST_OPENAI_GPT_4O_MINI_REASONING_PER_MILLION', 0),
                ],
            ],
        ],
    ],

];
