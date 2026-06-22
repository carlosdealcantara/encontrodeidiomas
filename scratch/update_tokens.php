<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

$tokens = [
    'Francês' => '78hTspvSudJWK7hSecVohUvqxauc82to',
    'Alemão' => 'D1HW9Ex9pjmVpThbvQpXVP1nShFEHTNy',
    'Inglês' => 'GGvENFkMmsBB9V7ZWpfty4X8BRWskkcR',
    'Espanhol' => '9eHg2MgyUHZ5VRS2u6LoV2EyXnRrUXmR',
    'Libras' => 'DJyQycooLiCg9A79nuA2XQ2rgGgdVYCN',
    'Japonês' => '5pPo6WShwn5o7L9r7PDB567YigPvjm4H',
    'Italiano' => 'GKKX4VsaruVRKyXBFKTDPrpag1tRSdMf',
    'Coreano' => 'FtWwSehmKK5FS6KYDMbDz9JJ6LWEBELN',
    'Chinês' => '3DCEfDssh4ffKZhmSLV6wXMvgdtk7ktn',
    'Russo' => '9VrMnun7v3JYFKH1EvAGBxYYHxVULfoG',
    'Polonês' => 'ByguMc6T8KgWK4GSgVVZbzyYbkH93bUN',
    'Português' => '5TBnpbPz45Ttx5xbh9ksSEoX79qXETn9',
    'Servo-Croata' => '2EuEcv9s2kkgtyxREkd9G8tkZG5v2LFx'
];

$updated = 0;
foreach ($tokens as $name => $token) {
    // The db has names like 'Inglês', 'Servo-Croata'
    $stmt = $conn->prepare("UPDATE languages SET odysee_auth_token = ? WHERE name LIKE ?");
    $stmt->execute([$token, "%$name%"]);
    $updated += $stmt->rowCount();
}

echo "Tokens updated: $updated";
