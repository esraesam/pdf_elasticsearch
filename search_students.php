<?php
require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;

function searchStudents($query) {
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
                    'nested' => [
                        'path' => 'students',
                        'query' => [
                            'match' => [
                                'students.name' => [
                                    'query' => $query,
                                    'analyzer' => 'arabic'
                                ]
                            ]
                        ],
                        'inner_hits' => new \stdClass()
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'students.name' => [
                            'type' => 'unified',
                            'fragment_size' => 150,
                            'number_of_fragments' => 3,
                            'no_match_size' => 150
                        ]
                    ]
                ]
            ]
        ];

        $response = $client->search($params);
        
        $results = array();
        foreach ($response['hits']['hits'] as $hit) {
            foreach ($hit['inner_hits']['students']['hits']['hits'] as $student) {
                $results[] = [
                    'name' => $student['_source']['name'],
                    'highlighted_name' => $student['highlight']['students.name'][0] ?? $student['_source']['name'],
                    'file_name' => $hit['_source']['file_name'],
                    'file_url' => $hit['_source']['file_url']
                ];
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error searching students: " . $e->getMessage());
        throw $e;
    }
}

// Main execution
try {
    $query = $_GET['query'] ?? '';

    if (empty($query)) {
        throw new Exception('Search query not provided');
    }

    $searchResults = searchStudents($query);
    
    echo json_encode([
        'results' => $searchResults
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}