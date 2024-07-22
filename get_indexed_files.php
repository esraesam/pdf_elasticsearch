<?php
header('Content-Type: application/json');

require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;

function getIndexedFiles() {
    try {
        $client = ClientBuilder::create()
            ->setHosts(['https://localhost:9200'])
            ->setBasicAuthentication('elastic', 'esra1999')
            ->setSSLVerification(false)
            ->build();

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
        $files = array_map(function($hit) {
            return $hit['_source'];
        }, $response['hits']['hits']);

        return $files;
    } catch (Exception $e) {
        error_log("Error fetching indexed files: " . $e->getMessage());
        throw $e;
    }
}

// Main execution
try {
    $indexedFiles = getIndexedFiles();
    echo json_encode(['files' => $indexedFiles]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}