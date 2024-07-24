<?php
header('Content-Type: application/json');

require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;
use Smalot\PdfParser\Parser;

function createElasticsearchClient() {
    try {
        $client = ClientBuilder::create()
            ->setHosts(['https://localhost:9200'])
            ->setBasicAuthentication('elastic', 'esra1999')
            ->setSSLVerification(false)  // Only for development
            ->build();
        error_log("Elasticsearch client created successfully");
        return $client;
    } catch (Exception $e) {
        error_log("Error creating Elasticsearch client: " . $e->getMessage());
        throw $e;
    }
}

function ensureIndexExists($client) {
    $indexName = 'pdf_files';
    try {
        $exists = $client->indices()->exists(['index' => $indexName]);
        if (!$exists) {
            $params = [
                'index' => $indexName,
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'file_name' => ['type' => 'text'],
                            'file_url' => ['type' => 'text'],
                            'upload_date' => [
                                'type' => 'date',
                                'format' => 'strict_date_optional_time||epoch_millis'
                            ],
                            'student_names' => ['type' => 'text']
                        ]
                    ]
                ]
            ];
            $client->indices()->create($params);
            error_log("Index 'pdf_files' created successfully");
        } else {
            error_log("Index 'pdf_files' already exists");
        }
    } catch (Exception $e) {
        error_log("Error ensuring index exists: " . $e->getMessage());
        throw $e;
    }
}

function extractStudentNames($pdfUrl) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfUrl);
        $text = $pdf->getText();
        
        // Split the text into lines
        $lines = explode("\n", $text);
        
        $students = array();
        
        foreach ($lines as $line) {
            // Match lines that start with a number or 'غ', followed by a 12-digit ID
            if (preg_match('/^(\d+|غ)\t(\d{12})(.+?)(\d|$)/', $line, $matches)) {
                $fullName = trim($matches[3]);
                
                // Remove any trailing numbers or special characters
                $fullName = preg_replace('/[\d\sغ]+$/', '', $fullName);
                
                // Trim any leading or trailing whitespace
                $fullName = trim($fullName);
                
                if (!empty($fullName)) {
                    // Split the name into words
                    $words = explode(' ', $fullName);
                    
                    // Reverse the letters in each word
                    $correctedWords = array_map(function($word) {
                        return implode('', array_reverse(preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY)));
                    }, $words);
                    
                    // Reverse the order of the corrected words
                    $correctedWords = array_reverse($correctedWords);
                    
                    // Join the corrected words back together
                    $correctedName = implode(' ', $correctedWords);
                    
                    $students[] = $correctedName;
                }
            }
        }
        
        error_log("Extracted " . count($students) . " student names");
        return $students;
    } catch (Exception $e) {
        error_log("Error extracting student names: " . $e->getMessage());
        throw $e;
    }
}

function indexPDF($pdfUrl, $fileName) {
    try {
        $client = createElasticsearchClient();
        ensureIndexExists($client);

        error_log("Extracting student names from PDF");
        $students = extractStudentNames($pdfUrl);

        $params = [
            'index' => 'pdf_files',
            'body' => [
                'file_name' => $fileName,
                'file_url' => $pdfUrl,
                'upload_date' => date('Y-m-d\TH:i:s\Z'), // ISO 8601 format
                'student_names' => $students
            ]
        ];

        error_log("Indexing PDF: " . json_encode($params));
        $response = $client->index($params);

        error_log("PDF indexed successfully. Response: " . json_encode($response));
        return [
            'status' => 'success',
            'message' => 'File indexed successfully.',
            'id' => $response['_id']
        ];
    } catch (Exception $e) {
        error_log("Error indexing PDF: " . $e->getMessage());
        throw $e;
    }
}

// Main execution
error_log("Received request to index PDF");
$pdfUrl = $_POST['pdfUrl'] ?? '';
$fileName = $_POST['fileName'] ?? '';

error_log("PDF URL: $pdfUrl");
error_log("File Name: $fileName");

if (empty($pdfUrl) || empty($fileName)) {
    error_log("PDF URL or file name not provided");
    echo json_encode([
        'status' => 'error',
        'message' => 'PDF URL or file name not provided'
    ]);
} else {
    try {
        $result = indexPDF($pdfUrl, $fileName);
        error_log("Indexing result: " . json_encode($result));
        echo json_encode($result);
    } catch (Exception $e) {
        error_log("Error in main execution: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}