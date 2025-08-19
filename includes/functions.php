<?php
function isDateLocked($pdo, $date) {
    $stmt = $pdo->prepare("SELECT * FROM locks WHERE locked_date = ?");
    $stmt->execute([$date]);
    return $stmt->fetch() !== false;
}

?>
