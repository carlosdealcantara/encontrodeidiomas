<?php
require_once 'config.php';
$conn = connectDB();

$migrationData = [
    'Carlos de Alcântara' => [
        'email' => 'carlodealcantarajr@gmail.com',
        'whatsapp' => '5561992666148',
        'instagram' => 'carlosdealcantara_',
        'linkedin' => 'https://www.linkedin.com/in/carlos-de-alc%C3%A2ntara-75b96a6b/',
        'github' => 'carlosdealcantara'
    ],
    'Ane' => ['email' => 'ane.eloa2017@gmail.com'],
    'Rhádila' => ['email' => 'grhadila@gmail.com'],
    'Társis' => ['email' => 'tltehele@gmail.com'],
    'Wellington' => ['email' => 'wellingtonlffilho@gmail.com'],
    'Caíque' => ['email' => 'caiquelima116@gmail.com'],
    'Jackelynne' => ['email' => 'jackepassos58@gmail.com'],
    'Alyce' => ['email' => 'alyce.a.ribeiro@gmail.com'],
    'Rosana' => ['email' => 'rosanapiai22@gmail.com'],
    'Isaac' => ['email' => 'isaacdep.oliveira@gmail.com'],
    'Daniel' => ['email' => 'danieldantas432@gmail.com'],
    'Paula' => [
        'whatsapp' => '5511989835981',
        'instagram' => 'bybellegard'
    ]
];

foreach ($migrationData as $name => $data) {
    // Busca o host pelo nome (case insensitive)
    $stmt = $conn->prepare("SELECT id, social_media_links FROM hosts WHERE full_name LIKE :name");
    $stmt->execute(['name' => '%' . $name . '%']);
    $host = $stmt->fetch();

    if ($host) {
        $existingSocial = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];
        
        // Faz o merge dos dados novos com os existentes (novos sobrescrevem)
        $newSocial = array_merge($existingSocial, $data);
        $jsonSocial = json_encode($newSocial, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $updateStmt = $conn->prepare("UPDATE hosts SET social_media_links = :social WHERE id = :id");
        $updateStmt->execute(['social' => $jsonSocial, 'id' => $host['id']]);
        echo "Updated: {$name}\n";
    } else {
        echo "Not found: {$name}\n";
    }
}
?>
