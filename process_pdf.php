<?php
require 'vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;
use Smalot\PdfParser\Parser;

function extractStudentNames($pdfUrl) {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfUrl);
    $text = $pdf->getText();
    
    // Split the text into lines
    $lines = explode("\n", $text);
    
    $students = array();
    $isStudentNameColumn = false;
    $studentNameColumnIndex = -1;
    
    foreach ($lines as $line) {
        $columns = preg_split('/\s+/', $line);
        
        // Check if this line contains the column headers
        if (in_array("اسم الطالب", $columns)) {
            $studentNameColumnIndex = array_search("اسم الطالب", $columns);
            $isStudentNameColumn = true;
            continue;
        }
        
        // If we've found the student name column and this line has enough columns
        if ($isStudentNameColumn && count($columns) > $studentNameColumnIndex) {
            $studentName = $columns[$studentNameColumnIndex];
            
            // Check if the student name is not empty and not a number (to avoid picking up other data)
            if (!empty($studentName) && !is_numeric($studentName)) {
                $students[] = array(
                    'name' => $studentName
                );
            }
        }
        
        // Stop processing if we've reached the end of the student list (you might need to adjust this condition)
        if ($isStudentNameColumn && strpos($line, 'المشاركون') !== false) {
            break;
        }
    }
    
    return $students;
}

function indexPDF($pdfUrl, $fileName) {
    try {
        $client = ClientBuilder::create()
            ->setHosts(['https://localhost:9200'])
            ->setBasicAuthentication('elastic', 'esra1999')
            ->setSSLVerification(false)
            ->build();

        $students = extractStudentNames($pdfUrl);

        $params = [
            'index' => 'pdf_files',
            'body' => [
                'file_name' => $fileName,
                'file_url' => $pdfUrl,
                'upload_date' => date('Y-m-d H:i:s'),
                'students' => $students
            ]
        ];

        $response = $client->index($params);
        return $response['_id'];
    } catch (Exception $e) {
        error_log("Error indexing PDF: " . $e->getMessage());
        throw $e;
    }
}

// Main execution
try {
    $pdfUrl = $_POST['pdfUrl'] ?? '';
    $fileName = $_POST['fileName'] ?? '';

    if (empty($pdfUrl) || empty($fileName)) {
        throw new Exception('PDF URL or file name not provided');
    }

    $indexedId = indexPDF($pdfUrl, $fileName);
    
    echo json_encode([
        'message' => 'PDF indexed successfully',
        'indexedId' => $indexedId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}