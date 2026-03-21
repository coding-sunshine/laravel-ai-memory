<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Vector Dimensions
    |--------------------------------------------------------------------------
    |
    | The number of dimensions for the embedding vectors. This should match
    | the output dimensions of your configured embedding model. Common values:
    | - 1536 (OpenAI text-embedding-3-small, text-embedding-ada-002)
    | - 3072 (OpenAI text-embedding-3-large)
    | - 1024 (Cohere embed-english-v3.0)
    |
    */

    'dimensions' => env('MEMORY_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | The minimum cosine similarity score (0.0 to 1.0) required for a memory
    | to be considered relevant during recall. Lower values return more results
    | but may include less relevant memories. Higher values are stricter.
    |
    */

    'similarity_threshold' => env('MEMORY_SIMILARITY_THRESHOLD', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Default Recall Limit
    |--------------------------------------------------------------------------
    |
    | The default maximum number of memories returned by the recall method.
    | This can be overridden per-call via the $limit parameter.
    |
    */

    'recall_limit' => env('MEMORY_RECALL_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Middleware Recall Limit
    |--------------------------------------------------------------------------
    |
    | The default number of memories injected into agent prompts by the
    | InjectMemory middleware. Keep this relatively low to avoid consuming
    | too much of the context window.
    |
    */

    'middleware_recall_limit' => env('MEMORY_MIDDLEWARE_RECALL_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Recall Oversample Factor
    |--------------------------------------------------------------------------
    |
    | When recalling memories, we first retrieve (limit × factor) candidates
    | via vector similarity search, then rerank them to return the top N.
    | Higher values improve reranking quality at the cost of more DB reads.
    |
    */

    'recall_oversample_factor' => env('MEMORY_RECALL_OVERSAMPLE_FACTOR', 2),

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The database table name used to store memories. Change this if you need
    | to avoid conflicts with existing tables in your application.
    |
    */

    'table' => env('MEMORY_TABLE', 'memories'),

    /*
    |--------------------------------------------------------------------------
    | Deduplication Threshold
    |--------------------------------------------------------------------------
    |
    | The cosine similarity threshold (0.0 to 1.0) above which a new memory
    | is considered a duplicate of an existing one. When a duplicate is
    | detected, the existing memory is updated instead of creating a new one.
    | Set to 1.0 to disable deduplication.
    |
    */

    'dedup_threshold' => env('MEMORY_DEDUP_THRESHOLD', 0.95),

    /*
    |--------------------------------------------------------------------------
    | Maximum Memories Per Context
    |--------------------------------------------------------------------------
    |
    | The maximum number of memories allowed per context (e.g., per user).
    | When this limit is exceeded after a store operation, older memories
    | are pruned according to the configured pruning strategy.
    | Set to 0 to disable the limit.
    |
    */

    'max_per_context' => env('MEMORY_MAX_PER_CONTEXT', 0),

    /*
    |--------------------------------------------------------------------------
    | Pruning Strategy
    |--------------------------------------------------------------------------
    |
    | The strategy used when pruning memories to enforce per-context limits.
    | Supported values: "oldest" (remove oldest first by created_at).
    |
    */

    'pruning_strategy' => env('MEMORY_PRUNING_STRATEGY', 'oldest'),

];
