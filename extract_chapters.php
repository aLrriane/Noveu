<?php
// extract_chapters.php
require_once 'config/db.php';
require_once 'classes/EpubParser.php';

session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (!isset($_GET['novel_id'])) { header("Location: index.php"); exit; }

$novel_id = $_GET['novel_id'];
$uid = $_SESSION['user_id'];

// 1. Verify Owner
$stmt = $pdo->prepare("SELECT * FROM novels WHERE id = ?");
$stmt->execute([$novel_id]);
$novel = $stmt->fetch();

if (!$novel || ($novel['uploaded_by'] != $uid && $_SESSION['role'] != 'admin')) {
    $_SESSION['swal'] = ['icon' => 'error', 'title' => 'Access Denied', 'text' => 'You do not own this content.'];
    header("Location: index.php"); exit;
}

$file_path = $novel['file_path'];
$ext = pathinfo($file_path, PATHINFO_EXTENSION);

if (strtolower($ext) !== 'epub' || !file_exists($file_path)) {
    $_SESSION['swal'] = ['icon' => 'error', 'title' => 'File Error', 'text' => 'File is missing or not an EPUB.'];
    header("Location: novel.php?id=$novel_id"); exit;
}

// 2. Extraction
@set_time_limit(300);

if (!class_exists('ZipArchive')) { 
    $_SESSION['swal'] = ['icon' => 'error', 'title' => 'Server Error', 'text' => 'ZipArchive extension missing on server.'];
    header("Location: novel.php?id=$novel_id"); exit;
}

$parser = new EpubParser($file_path);
$chapters = $parser->parse();
$count = 0;

if ($chapters) {
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO chapters (novel_id, title, chapter_number, file_path) VALUES (?, ?, ?, ?)";
        $insert = $pdo->prepare($sql);

        // Determine start number
        $max = $pdo->query("SELECT MAX(chapter_number) FROM chapters WHERE novel_id=$novel_id")->fetchColumn();
        $start_num = $max ? $max + 1 : 1;

        if (!is_dir('uploads/chapters')) mkdir('uploads/chapters', 0777, true);

        foreach ($chapters as $chap) {
            $txtFileName = "chapter_{$novel_id}_{$start_num}_" . uniqid() . ".txt";
            $txtPath = "uploads/chapters/" . $txtFileName;
            
            if(file_put_contents($txtPath, $chap['content'])) {
                $insert->execute([$novel_id, $chap['title'], $start_num, $txtPath]);
                $start_num++;
                $count++;
            }
        }
        $pdo->commit();
        
        // SUCCESS POPUP!
        $_SESSION['swal'] = [
            'icon' => 'success', 
            'title' => 'Extraction Complete!', 
            'text' => "Successfully extracted $count chapters."
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal'] = ['icon' => 'error', 'title' => 'Database Error', 'text' => $e->getMessage()];
    }
} else {
    $_SESSION['swal'] = ['icon' => 'warning', 'title' => 'Extraction Failed', 'text' => 'Could not read chapters. The EPUB might be DRM protected or empty.'];
}

header("Location: novel.php?id=$novel_id");
exit;
?>