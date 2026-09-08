<?php

namespace App\Controllers;

use App\Core\Database;

class DocumentController
{
    public function index(): string
    {
        $pdo = Database::connection();
        $documents = $pdo->query('SELECT * FROM documents ORDER BY id DESC')->fetchAll();

        $data = [
            'title' => 'Documents — Sarura Fuel',
            'documents' => array_map(function ($document) {
                return [
                    'name' => $document['name'],
                    'type' => $document['type'],
                    'file' => $document['file_name'] ?? 'document.pdf',
                    'expiry' => $document['uploaded_at'] ?? date('Y-m-d'),
                    'status' => $document['status'] ?? 'Uploaded',
                ];
            }, $documents),
        ];

        return view('documents.index', $data);
    }

    public function upload(): void
    {
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document'];
            $maxSizeBytes = 10 * 1024 * 1024; // 10MB limit

            if ($file['size'] > $maxSizeBytes) {
                flash('error', 'File exceeds maximum size limit of 10MB.');
                redirect('/documents');
            }

            $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'docx', 'xlsx'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                flash('error', 'Invalid file type. Allowed: PDF, PNG, JPG, JPEG, WEBP, DOCX, XLSX.');
                redirect('/documents');
            }

            $uploadDir = base_path('storage/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $newFileName = uniqid('doc_', true) . '_' . substr($safeName, 0, 30) . '.' . $extension;
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $pdo = Database::connection();
                $pdo->prepare('INSERT INTO documents (name, type, file_name, uploaded_at, status) VALUES (?, ?, ?, ?, ?)')
                    ->execute([
                        'Uploaded document: ' . $file['name'],
                        strtoupper($extension),
                        $newFileName,
                        date('Y-m-d'),
                        'Uploaded',
                    ]);
            }
        }

        redirect('/documents');
    }
}
