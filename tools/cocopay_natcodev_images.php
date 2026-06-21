<?php

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cocopay;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function updateImage(PDO $pdo, int $id, string $image): void
{
    $stmt = $pdo->prepare('SELECT data_values FROM frontends WHERE id = ?');
    $stmt->execute([$id]);
    $data = json_decode((string) $stmt->fetchColumn(), true) ?: [];
    $data['has_image'] = $data['has_image'] ?? '1';
    $data['image'] = $image;

    $stmt = $pdo->prepare('UPDATE frontends SET data_values = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([json_encode($data, JSON_UNESCAPED_SLASHES), $id]);
}

updateImage($pdo, 39, 'natcodev-africa-dwarf-coconut-hero.png');
updateImage($pdo, 24, 'natcodev-africa-coconut-training.png');
updateImage($pdo, 50, 'natcodev-africa-coconut-aggregation.png');
updateImage($pdo, 112, 'natcodev-dwarf-coconut-nursery.png');
updateImage($pdo, 113, 'natcodev-dwarf-coconut-nursery.png');
updateImage($pdo, 114, 'natcodev-dwarf-coconut-nursery.png');
updateImage($pdo, 82, 'natcodev-africa-dwarf-coconut-breadcrumb.png');

echo "NATCODEV_IMAGES_UPDATED\n";
