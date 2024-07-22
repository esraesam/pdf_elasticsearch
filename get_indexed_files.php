<?php
header('Content-Type: application/json; charset=utf-8');

require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;

function createElasticsearchClient() {
    try {
        $client = ClientBuilder::create()
            ->setHosts(['https://localhost:9200'])
            ->setBasicAuthentication('elastic', 'esra1999')
            ->setSSLVerification(false)  // Only use this for development
            ->build();

        // Test the connection
        $info = $client->info();
        error_log("Elasticsearch connection successful. Cluster info: " . json_encode($info));

        return $client;
    } catch (Exception $e) {
        error_log("Error creating Elasticsearch client: " . $e->getMessage());
        throw new Exception("Failed to connect to Elasticsearch: " . $e->getMessage());
    }
}

function ensureIndexExists($client) {
    $indexName = 'pdf_files';
    try {
        $exists = $client->indices()->exists(['index' => $indexName]);
        error_log("Index existence check result: " . json_encode($exists));

        if (!$exists) {
            error_log("Index does not exist. Attempting to create...");
            $createParams = [
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
            $response = $client->indices()->create($createParams);
            error_log("Index creation response: " . json_encode($response));

            // Double-check that the index was created
            $exists = $client->indices()->exists(['index' => $indexName]);
            if (!$exists) {
                throw new Exception("Failed to create index despite successful API call");
            }
        } else {
            error_log("Index already exists");
        }
    } catch (Exception $e) {
        error_log("Error in ensureIndexExists: " . $e->getMessage());
        throw $e;
    }
}

function getIndexedFiles() {
    try {
        $client = createElasticsearchClient();
        
        ensureIndexExists($client);

        $params = [
            'index' => 'pdf_files',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass()
                ],
                '_source' => ['file_name', 'file_url', 'upload_date']
            ]
        ];

        $response = $client->search($params);
        
        $files = array();
        foreach ($response['hits']['hits'] as $hit) {
            $files[] = [
                'file_name' => $hit['_source']['file_name'],
                'file_url' => $hit['_source']['file_url'],
                'upload_date' => $hit['_source']['upload_date']
            ];
        }

        return $files;
    } catch (Exception $e) {
        error_log("Error fetching indexed files: " . $e->getMessage());
        throw $e;
    }
}

// Main execution
try {
    $indexedFiles = getIndexedFiles();
    echo json_encode(['files' => $indexedFiles], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

exit;