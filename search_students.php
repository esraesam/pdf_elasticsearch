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
                    'bool' => [
                        'should' => [
                            [
                                'wildcard' => [
                                    'student_names' => "$query"
                                ]
                            ],
                            [
                                'match' => [
                                    'student_names' => [
                                        'query' => $query,
                                        'fuzziness' => 'AUTO'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'student_names' => new \stdClass()
                    ]
                ]
            ]
        ];

        $response = $client->search($params);
        
        $results = array();
        foreach ($response['hits']['hits'] as $hit) {
            $fileName = $hit['_source']['file_name'];
            $matchedStudents = array_filter($hit['_source']['student_names'], function($name) use ($query) {
                return mb_stripos($name, $query) !== false;
            });

            foreach ($matchedStudents as $student) {
                $results[] = [
                    'name' => $student,
                    'file_name' => $fileName,
                    'highlighted_name' => isset($hit['highlight']['student_names']) 
                        ? $hit['highlight']['student_names'][0] 
                        : $student
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
header('Content-Type: application/json; charset=utf-8');

try {
    $query = $_GET['query'] ?? '';

    if (mb_strlen($query) < 2) {
        echo json_encode(['results' => []]);
    } else {
        $searchResults = searchStudents($query);
        echo json_encode(['results' => $searchResults], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}