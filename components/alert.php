<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

if ($success_message) {
    echo '
    <div id="alert-success" class="rounded-md bg-green-50 p-4 mb-6 border border-green-200">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800">' . htmlspecialchars($success_message) . '</p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button type="button" onclick="document.getElementById(\'alert-success\').style.display=\'none\'" class="inline-flex bg-green-50 rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-green-50 focus:ring-green-600">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>';
    unset($_SESSION['success_message']);
}

if ($error_message) {
    echo '
    <div id="alert-error" class="rounded-md bg-red-50 p-4 mb-6 border border-red-200">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-times-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800">' . htmlspecialchars($error_message) . '</p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button type="button" onclick="document.getElementById(\'alert-error\').style.display=\'none\'" class="inline-flex bg-red-50 rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>';
    unset($_SESSION['error_message']);
}
?>
