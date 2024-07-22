<?php
header('Content-Type: application/json; charset=utf-8');

require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;

function createElasticsearchClient() {
    return ClientBuilder::create()
        ->setHosts(['https://localhost:9200'])
        ->setBasicAuthentication('elastic', 'esra1999')
        ->setSSLVerification(false)  // Only for development
        ->build();
}

function forceCreateIndex($client) {
    $indexName = 'pdf_files';
    $params = [
        'index' => $indexName,
        'body' => [
            'mappings' => [
                'properties' => [
                    'file_name' => ['type' => 'text'],
                    'file_url' => ['type' => 'text'],
                    'upload_date' => ['type' => 'date'],
                    'student_names' => ['type' => 'text']
                ]
            ]
        ]
    ];

    try {
        $client->indices()->create($params);
    } catch (\Exception $e) {
        // If the index already exists, that's fine, we can continue
        if (!strpos($e->getMessage(), 'resource_already_exists_exception')) {
            throw $e;
        }
    }
}

function getIndexedStudents() {
    $client = createElasticsearchClient();
    forceCreateIndex($client);

    $params = [
        'index' => 'pdf_files',
        'body' => [
            'query' => [
                'match_all' => new \stdClass()
            ],
            '_source' => ['file_name', 'student_names']
        ]
    ];

    $response = $client->search($params);
    
    $results = array();
    foreach ($response['hits']['hits'] as $hit) {
        $results[] = [
            'file_name' => $hit['_source']['file_name'],
            'students' => $hit['_source']['student_names'] ?? []
        ];
    }

    return $results;
}

// Main execution
try {
    $indexedStudents = getIndexedStudents();
    echo json_encode(['indexed_students' => $indexedStudents], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}