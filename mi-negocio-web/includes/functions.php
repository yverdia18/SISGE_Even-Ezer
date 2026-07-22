<?php
// includes/functions.php
function formatMoney($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function getStockBadge($stock, $minimo = 10) {
    if ($stock < $minimo) {
        return '<span class="badge bg-warning text-dark">' . $stock . ' unidades</span>';
    }
    return '<span class="badge bg-success">' . $stock . ' unidades</span>';
}
?>