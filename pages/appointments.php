<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$sidebar_firstName = $_SESSION['first_name'];

$stmt = $conn->prepare("SELECT first_name, last_name, email, specialization, license_number, bio, profile_picture_url FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$derma = $result->fetch_assoc();

$sidebar_firstName = htmlspecialchars($derma['first_name'] ?? 'Dermatologist');
$profilePicturePath = isset($derma['profile_picture_url']) && !empty($derma['profile_picture_url']) ? '../' . htmlspecialchars($derma['profile_picture_url']) : 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';

// Get all appointments for calendar view
$sql = "SELECT appointment_id, patient_name, appointment_date, appointment_time, status FROM appointments WHERE dermatologist_id = ? ORDER BY appointment_date ASC, appointment_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count appointments by status
$counts = [
    'pending' => 0,
    'scheduled' => 0,
    'finished' => 0,
    'cancelled' => 0,
];

$count_sql = "SELECT status, COUNT(*) as count FROM appointments WHERE dermatologist_id = ? GROUP BY status";
if ($count_stmt = $conn->prepare($count_sql)) {
    $count_stmt->bind_param("i", $dermatologistId);
    $count_stmt->execute();
    $result = $count_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status_key = strtolower($row['status']);
        if ($status_key === 'completed') {
            $status_key = 'finished';
        }
        if (array_key_exists($status_key, $counts)) {
            $counts[$status_key] += $row['count'];
        }
    }
    $count_stmt->close();
}

$otherDermatologistsStmt = $conn->prepare("SELECT dermatologist_id, first_name, last_name FROM dermatologists WHERE dermatologist_id != ?");
$otherDermatologistsStmt->bind_param("i", $dermatologistId);
$otherDermatologistsStmt->execute();
$otherDermatologists = $otherDermatologistsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$otherDermatologistsStmt->close();



function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'Scheduled':
            return 'bg-green-100 text-green-800';
        case 'Pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'Completed':
            return 'bg-blue-100 text-blue-800';
        case 'Cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Calendar - DermaSculpt</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📅</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FullCalendar CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <style>
        :root {
            --sidebar-width: 256px;
            --sidebar-width-collapsed: 80px;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        @import url('https://rsms.me/inter/inter.css');

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #a8a8a8;
            border-radius: 10px;
        }

        #sidebar {
            width: var(--sidebar-width);
            transition: width 0.3s ease-in-out;
        }

        #sidebar.collapsed {
            width: var(--sidebar-width-collapsed);
        }

        #sidebar.collapsed .sidebar-text,
        #sidebar.collapsed .sidebar-logo-text,
        #sidebar.collapsed .profile-info {
            display: none;
        }

        .main-content {
            transition: margin-left 0.3s ease-in-out;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
        }

        #sidebar.collapsed+.main-content {
            margin-left: var(--sidebar-width-collapsed);
            width: calc(100% - var(--sidebar-width-collapsed));
        }

        .sidebar-link.active {
            background-color: #3b82f6;
            color: white;
        }

        .tab-link.active {
            color: #2563eb;
            border-color: #2563eb;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal {
            transition: visibility 0s linear 0.3s, opacity 0.3s ease-in-out;
            visibility: hidden;
            opacity: 0;
        }

        .modal:not(.hidden) {
            transition: visibility 0s linear 0s;
            visibility: visible;
            opacity: 1;
        }

        .modal-content {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
            transform: scale(0.95);
            opacity: 0;
        }

        .modal:not(.hidden) .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .ai-reply,
        .user-prompt {
            padding: 0.75rem;
            border-radius: 0.75rem;
            max-width: 90%;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .ai-reply {
            background-color: #f3f4f6;
            align-self: flex-start;
            border-top-left-radius: 0.125rem;
        }

        .user-prompt {
            background-color: #dbeafe;
            color: #1e3a8a;
            align-self: flex-end;
            border-top-right-radius: 0.125rem;
        }

        .ai-quick-btn {
            background-color: #e5e7eb;
            color: #374151;
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            transition: background-color 0.2s;
            flex-shrink: 0;
        }

        .ai-quick-btn:hover {
            background-color: #d1d5db;
        }

        /* FullCalendar custom styles */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            padding: 2px 6px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
        }

        .fc-event-pending {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border-left: 3px solid #f59e0b !important;
        }

        .fc-event-scheduled {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
            border-left: 3px solid #10b981 !important;
        }

        .fc-event-completed {
            background-color: #dbeafe !important;
            color: #1e40af !important;
            border-left: 3px solid #3b82f6 !important;
        }

        .fc-event-cancelled {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
            border-left: 3px solid #ef4444 !important;
        }

        /* Ensure text is always visible */
        .fc-event-title {
            color: inherit !important;
            font-weight: 600 !important;
        }

        .fc-toolbar-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            color: #0e7490 !important;
        }

        .fc-button-primary {
            background-color: #0891b2 !important;
            border-color: #0891b2 !important;
        }

        .fc-button-primary:hover {
            background-color: #0e7490 !important;
            border-color: #0e7490 !important;
        }

        .fc-daygrid-day-number {
            color: #374151 !important;
            font-weight: 500 !important;
        }

        .fc-col-header-cell {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            color: #475569 !important;
        }

        #calendar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-cyan-100 text-gray-800">
    <?php include '../components/sidebar.php'; ?>
    <div class="main-content flex flex-col h-screen">
        <header class="bg-white shadow-sm flex items-center justify-between p-4 h-16 flex-shrink-0 z-30">
            <button id="sidebar-toggle" onclick="toggleSidebar()" class="text-cyan-600 hover:text-cyan-800"><i class="fas fa-bars fa-xl"></i></button>
            <div class="relative">
                <button onclick="toggleDropdown()" class="flex items-center space-x-3">
                    <span class="hidden sm:inline text-sm font-medium text-cyan-700">Dr. <?php echo $sidebar_firstName; ?></span>
                    <img class="h-10 w-10 rounded-full object-cover border-2 border-transparent hover:border-cyan-500" src="<?php echo $profilePicturePath; ?>" alt="User avatar">
                </button>
                <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                    <a href="profile.php" class="block px-4 py-2 text-sm text-cyan-700 hover:bg-cyan-50">Your Profile</a>
                    <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-cyan-50">Logout</a>
                </div>
            </div>
        </header>


        <main class="flex-1 p-4 sm:p-6 overflow-y-auto" id="mainContent">
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h2 class="text-3xl font-extrabold text-cyan-700">Appointments Calendar</h2>
                    <p class="text-cyan-800 mt-1">View and manage patient appointments in calendar format.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openCreateAppointmentModal()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-plus"></i> Create Appointment
                    </button>
                    <button onclick="manualRefresh()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button onclick="toggleAIAssistant()" id="aiToggleBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-robot"></i> AI Assistant
                    </button>
                </div>
            </div>

            <!-- Status Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800">Pending</p>
                            <p class="text-2xl font-bold text-yellow-900"><?php echo $counts['pending']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-calendar-check text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">Scheduled</p>
                            <p class="text-2xl font-bold text-green-900"><?php echo $counts['scheduled']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-800">Finished</p>
                            <p class="text-2xl font-bold text-blue-900"><?php echo $counts['finished']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">Cancelled</p>
                            <p class="text-2xl font-bold text-red-900"><?php echo $counts['cancelled']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Toolbar -->
            <div id="bulkActionsToolbar" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <span class="text-blue-800 font-medium">
                            <span id="selectedCount">0</span> appointments selected
                        </span>
                        <button onclick="toggleSelectionMode()" class="text-blue-600 hover:text-blue-800 text-sm">
                            Exit Selection Mode
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="bulkAccept()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                            <i class="fas fa-check"></i> Accept Selected
                        </button>
                        <button onclick="bulkCancel()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                            <i class="fas fa-times"></i> Cancel Selected
                        </button>
                        <button onclick="clearSelection()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                            Clear Selection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Selection Mode Toggle -->
            <div class="mb-4">
                <button onclick="toggleSelectionMode()" id="selectionModeBtn" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2">
                    <i class="fas fa-check-square"></i> Enable Bulk Selection
                </button>
            </div>

            <!-- Calendar Container -->
            <div class="flex flex-col gap-6">
                <div class="w-full">
                    <div id="calendar"></div>
                </div>

                <aside id="aiAssistantSidebar" class="w-full lg:w-[350px] flex-shrink-0 bg-white/80 border-l border-cyan-100 flex flex-col fixed right-0 top-16 bottom-0 h-[calc(100vh-4rem)] z-20 rounded-l-2xl shadow-xl transform translate-x-full transition-transform duration-300 ease-in-out hidden">
                    <div class="flex items-center gap-2 px-4 pt-4 pb-2 border-b border-cyan-100">
                        <span class="text-cyan-600"><i class="fas fa-bolt fa-lg"></i></span>
                        <h2 class="text-lg font-semibold text-cyan-700">AI Assistant</h2>
                    </div>

                    <div id="aiChatHistory" class="flex-1 p-4 space-y-4 overflow-y-auto flex flex-col">
                        <div id="ai-initial-greeting" class="ai-reply">
                            <p class="text-sm">Hello, Dr. <?php echo htmlspecialchars($sidebar_firstName); ?>. I can help you analyze the appointments in your current view. Ask me a question or use one of the suggestions below.</p>
                        </div>
                    </div>

                    <div class="p-4 border-t border-gray-200 bg-white">
                        <div class="flex gap-2 mb-2 overflow-x-auto pb-2">
                            <button type="button" class="ai-quick-btn" onclick="setAIPrompt('How many appointments do I have today?')"><i class="fas fa-calendar-day mr-1"></i>Today</button>
                            <button type="button" class="ai-quick-btn" onclick="setAIPrompt('Which appointments need my attention?')"><i class="fas fa-exclamation-triangle mr-1"></i>Priority</button>
                            <button type="button" class="ai-quick-btn" onclick="setAIPrompt('Analyze my schedule efficiency')"><i class="fas fa-chart-line mr-1"></i>Efficiency</button>
                            <button type="button" class="ai-quick-btn" onclick="clearAIChat()"><i class="fas fa-trash-alt mr-1"></i>Clear</button>
                        </div>
                        <form id="aiAssistantForm" class="flex items-center gap-2">
                            <input type="text" id="aiPrompt" class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 text-sm" placeholder="Ask about your appointments..." required autocomplete="off">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center justify-center h-10 w-10 flex-shrink-0"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </aside>

            </div>

        </main>
    </div>

    <div id="detailsModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50" onclick="closeModal('detailsModal')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-xl font-semibold">Appointment Details</h3><button onclick="closeModal('detailsModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div id="detailsModalContent" class="p-6 overflow-y-auto">
                <div class="text-center p-8"><i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i></div>
            </div>
        </div>
    </div>

    <div id="rescheduleModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50" onclick="closeModal('rescheduleModal')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-xl font-semibold">Reschedule Appointment</h3><button onclick="closeModal('rescheduleModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <form id="rescheduleForm" class="p-6 space-y-4">
                <input type="hidden" id="reschedule_appointment_id" name="appointment_id">
                <div>
                    <label for="reschedule_date" class="block text-sm font-medium text-gray-700">New Date</label>
                    <input type="date" id="reschedule_date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="reschedule_time" class="block text-sm font-medium text-gray-700">New Time</label>
                    <input type="time" id="reschedule_time" name="appointment_time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="text-right pt-4">
                    <button type="button" onclick="closeModal('rescheduleModal')" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-300 mr-2">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="transferModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50" onclick="closeModal('transferModal')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-xl font-semibold">Transfer Appointment</h3><button onclick="closeModal('transferModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <form id="transferForm" class="p-6 space-y-4">
                <input type="hidden" id="transfer_appointment_id" name="appointment_id">
                <div>
                    <label for="transfer_dermatologist_id" class="block text-sm font-medium text-gray-700">Transfer to</label>
                    <select id="transfer_dermatologist_id" name="new_dermatologist_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <?php if (empty($otherDermatologists)) : ?>
                            <option disabled>No other dermatologists available</option>
                        <?php else : ?>
                            <?php foreach ($otherDermatologists as $derma) : ?>
                                <option value="<?php echo $derma['dermatologist_id']; ?>">Dr. <?php echo htmlspecialchars($derma['first_name'] . ' ' . $derma['last_name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="text-right pt-4">
                    <button type="button" onclick="closeModal('transferModal')" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-300 mr-2">Cancel</button>
                    <button type="submit" class="bg-purple-600 text-white py-2 px-6 rounded-lg hover:bg-purple-700" <?php echo empty($otherDermatologists) ? 'disabled' : ''; ?>>Confirm Transfer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Appointment Modal -->
    <div id="createAppointmentModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50" onclick="closeModal('createAppointmentModal')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-xl font-semibold text-purple-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i>Create New Appointment
                </h3>
                <button onclick="closeModal('createAppointmentModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto">
                <form id="createAppointmentForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <input type="text" id="patient_name" name="patient_name" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Patient Name" required>
                            <label for="patient_name" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Patient Name</label>
                        </div>
                        <div class="relative">
                            <input type="email" id="patient_email" name="email" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Patient Email" required>
                            <label for="patient_email" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Patient Email</label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <input type="tel" id="patient_phone" name="phone_number" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Phone Number">
                            <label for="patient_phone" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Phone Number</label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <input type="date" id="appointment_date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" required>
                            <label for="appointment_date" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700">Appointment Date</label>
                        </div>
                        <div class="relative">
                            <input type="time" id="appointment_time" name="appointment_time" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" required>
                            <label for="appointment_time" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700">Appointment Time</label>
                        </div>
                    </div>

                    <div class="relative">
                        <textarea id="reason_for_appointment" name="reason_for_appointment" rows="3" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Reason for Appointment"></textarea>
                        <label for="reason_for_appointment" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Reason for Appointment</label>
                    </div>

                    <div class="relative">
                        <textarea id="dermatologist_notes" name="dermatologist_notes" rows="3" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Doctor's Notes (Optional)"></textarea>
                        <label for="dermatologist_notes" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Doctor's Notes (Optional)</label>
                    </div>

                    <div class="bg-purple-50 border border-purple-200 p-4 rounded-lg">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                            <h4 class="font-medium text-purple-800">Appointment Information</h4>
                        </div>
                        <ul class="text-sm text-purple-700 space-y-1">
                            <li>• New appointments will be created with "Scheduled" status</li>
                            <li>• Patient will receive email notification if email is provided</li>
                            <li>• You can modify appointment details later if needed</li>
                        </ul>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeModal('createAppointmentModal')" class="bg-gray-200 text-gray-800 py-2 px-6 rounded-lg hover:bg-gray-300 font-semibold">
                            Cancel
                        </button>
                        <button type="submit" id="createAppointmentBtn" class="bg-purple-600 text-white py-2 px-6 rounded-lg hover:bg-purple-700 font-semibold flex items-center">
                            <i class="fas fa-plus mr-2"></i>Create Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Calendar-based Appointment Modal -->
    <div id="calendarAppointmentModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50" onclick="closeModal('calendarAppointmentModal')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-xl font-semibold text-purple-700 flex items-center">
                    <i class="fas fa-calendar-plus mr-2"></i>Book Appointment for <span id="selectedDateDisplay" class="text-cyan-600 ml-2"></span>
                </h3>
                <button onclick="closeModal('calendarAppointmentModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto">
                <!-- Step 1: Time Selection -->
                <div id="timeSelectionStep" class="space-y-6">
                    <div class="bg-gradient-to-r from-purple-50 to-cyan-50 p-4 rounded-lg border border-purple-200">
                        <h4 class="font-semibold text-purple-800 mb-2 flex items-center">
                            <i class="fas fa-clock mr-2"></i>Select Available Time Slot
                        </h4>
                        <p class="text-sm text-purple-600">Choose from available time slots below. Unavailable times are disabled.</p>
                    </div>
                    
                    <div id="timeSlotContainer" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <!-- Time slots will be generated here -->
                    </div>
                    
                    <div class="text-center">
                        <button type="button" onclick="closeModal('calendarAppointmentModal')" class="bg-gray-200 text-gray-800 py-2 px-6 rounded-lg hover:bg-gray-300 font-semibold">
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Step 2: Patient Details Form -->
                <div id="patientDetailsStep" class="space-y-6 hidden">
                    <div class="bg-gradient-to-r from-green-50 to-cyan-50 p-4 rounded-lg border border-green-200">
                        <h4 class="font-semibold text-green-800 mb-2 flex items-center">
                            <i class="fas fa-user mr-2"></i>Patient Information
                        </h4>
                        <p class="text-sm text-green-600">
                            Selected: <span id="selectedTimeDisplay" class="font-semibold"></span> on <span id="selectedDateDisplay2" class="font-semibold"></span>
                        </p>
                    </div>

                    <form id="calendarAppointmentForm" class="space-y-6">
                        <input type="hidden" id="calendar_appointment_date" name="appointment_date">
                        <input type="hidden" id="calendar_appointment_time" name="appointment_time">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="relative">
                                <input type="text" id="calendar_patient_name" name="patient_name" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Full Name" required>
                                <label for="calendar_patient_name" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Patient Full Name</label>
                            </div>
                            <div class="relative">
                                <input type="email" id="calendar_patient_email" name="email" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Email Address" required>
                                <label for="calendar_patient_email" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Email Address</label>
                            </div>
                        </div>

                        <div class="relative">
                            <input type="tel" id="calendar_patient_phone" name="phone_number" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Phone Number">
                            <label for="calendar_patient_phone" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Phone Number</label>
                        </div>

                        <div class="relative">
                            <textarea id="calendar_reason_for_appointment" name="reason_for_appointment" rows="3" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Reason for Appointment"></textarea>
                            <label for="calendar_reason_for_appointment" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Reason for Appointment</label>
                        </div>

                        <div class="relative">
                            <textarea id="calendar_dermatologist_notes" name="dermatologist_notes" rows="3" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-purple-500 focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Doctor's Notes (Optional)"></textarea>
                            <label for="calendar_dermatologist_notes" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-purple-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-purple-700">Doctor's Notes (Optional)</label>
                        </div>

                        <div class="flex justify-between gap-3 pt-4 border-t">
                            <button type="button" onclick="backToTimeSelection()" class="bg-gray-200 text-gray-800 py-2 px-6 rounded-lg hover:bg-gray-300 font-semibold flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Time Selection
                            </button>
                            <button type="submit" id="calendarCreateAppointmentBtn" class="bg-purple-600 text-white py-2 px-6 rounded-lg hover:bg-purple-700 font-semibold flex items-center">
                                <i class="fas fa-plus mr-2"></i>Create Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modals -->
    <div id="confirmationModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 id="confirmModalTitle" class="text-xl font-semibold">Confirm Action</h3>
                <button onclick="closeConfirmationModal()" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="p-6">
                <div id="confirmModalIcon" class="text-center mb-4">
                    <!-- Icon will be inserted here -->
                </div>
                <p id="confirmModalMessage" class="text-gray-700 text-center mb-6">Are you sure you want to proceed?</p>
                <div class="flex justify-end gap-3">
                    <button onclick="closeConfirmationModal()" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-300 font-semibold">
                        Cancel
                    </button>
                    <button id="confirmModalButton" class="py-2 px-4 rounded-lg font-semibold text-white">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Alert Modal -->
    <div id="alertModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 id="alertModalTitle" class="text-xl font-semibold">Alert</h3>
                <button onclick="closeAlertModal()" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="p-6">
                <div id="alertModalIcon" class="text-center mb-4">
                    <!-- Icon will be inserted here -->
                </div>
                <p id="alertModalMessage" class="text-gray-700 text-center mb-6">Message</p>
                <div class="text-center">
                    <button onclick="closeAlertModal()" id="alertModalButton" class="py-2 px-6 rounded-lg font-semibold text-white">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner Component -->
    <div class="loading-spinner hidden">
        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <script>
        // Global appointments data for both AI assistant and calendar
        const appointments = <?php
                                echo json_encode(array_map(function ($appt) {
                                    return [
                                        'id' => $appt['appointment_id'],
                                        'date' => $appt['appointment_date'],
                                        'time' => $appt['appointment_time'],
                                        'patient' => $appt['patient_name'],
                                        'status' => $appt['status']
                                    ];
                                }, $appointments));
                                ?>;

        // Availability data
        let availabilityData = {
            daysOff: [],
            bookedSlots: {}
        };
        let calendar; // Store calendar reference globally

        // Bulk selection variables
        let selectedAppointments = new Set();
        let isSelectionMode = false;

        // Custom Modal Functions
        function showConfirmation(title, message, confirmText, confirmColor, onConfirm, icon = 'question') {
            const modal = document.getElementById('confirmationModal');
            const titleEl = document.getElementById('confirmModalTitle');
            const messageEl = document.getElementById('confirmModalMessage');
            const buttonEl = document.getElementById('confirmModalButton');
            const iconEl = document.getElementById('confirmModalIcon');

            titleEl.textContent = title;
            messageEl.textContent = message;
            buttonEl.textContent = confirmText;
            buttonEl.className = `py-2 px-4 rounded-lg font-semibold text-white ${confirmColor}`;

            // Set icon
            const iconMap = {
                'question': '<i class="fas fa-question-circle text-4xl text-blue-500"></i>',
                'warning': '<i class="fas fa-exclamation-triangle text-4xl text-yellow-500"></i>',
                'danger': '<i class="fas fa-exclamation-circle text-4xl text-red-500"></i>'
            };
            iconEl.innerHTML = iconMap[icon] || iconMap['question'];

            // Set up confirm button click
            buttonEl.onclick = () => {
                // Don't close modal immediately - let onConfirm handle the API call and then close
                onConfirm();
            };

            modal.classList.remove('hidden');
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
        }

        function showAlert(title, message, type = 'info', callback = null) {
            const modal = document.getElementById('alertModal');
            const titleEl = document.getElementById('alertModalTitle');
            const messageEl = document.getElementById('alertModalMessage');
            const buttonEl = document.getElementById('alertModalButton');
            const iconEl = document.getElementById('alertModalIcon');

            titleEl.textContent = title;
            messageEl.textContent = message;

            // Set button color and icon based on type
            const typeConfig = {
                'success': {
                    color: 'bg-green-600 hover:bg-green-700',
                    icon: '<i class="fas fa-check-circle text-4xl text-green-500"></i>'
                },
                'error': {
                    color: 'bg-red-600 hover:bg-red-700',
                    icon: '<i class="fas fa-times-circle text-4xl text-red-500"></i>'
                },
                'warning': {
                    color: 'bg-yellow-600 hover:bg-yellow-700',
                    icon: '<i class="fas fa-exclamation-triangle text-4xl text-yellow-500"></i>'
                },
                'info': {
                    color: 'bg-blue-600 hover:bg-blue-700',
                    icon: '<i class="fas fa-info-circle text-4xl text-blue-500"></i>'
                }
            };

            const config = typeConfig[type] || typeConfig['info'];
            buttonEl.className = `py-2 px-6 rounded-lg font-semibold text-white ${config.color}`;
            iconEl.innerHTML = config.icon;

            // Set up callback
            buttonEl.onclick = () => {
                closeAlertModal();
                if (callback) callback();
            };

            modal.classList.remove('hidden');
        }

        function closeAlertModal() {
            document.getElementById('alertModal').classList.add('hidden');
        }

        // Loading Spinner Functions
        function showButtonLoading(button, originalText) {
            const spinner = document.querySelector('.loading-spinner').cloneNode(true);
            spinner.classList.remove('hidden');
            button.innerHTML = '';
            button.appendChild(spinner);
            button.appendChild(document.createTextNode(' Loading...'));
            button.disabled = true;
            button.dataset.originalText = originalText;
        }

        function hideButtonLoading(button) {
            const originalText = button.dataset.originalText || 'Submit';
            button.innerHTML = originalText;
            button.disabled = false;
            delete button.dataset.originalText;
        }

        const aiAssistantForm = document.getElementById('aiAssistantForm');
        const aiPromptInput = document.getElementById('aiPrompt');
        const aiChatHistory = document.getElementById('aiChatHistory');

        function setAIPrompt(text) {
            aiPromptInput.value = text;
            aiPromptInput.focus();
        }

        function clearAIChat() {
            aiChatHistory.innerHTML = '';
            const greetingDiv = document.createElement('div');
            greetingDiv.id = 'ai-initial-greeting';
            greetingDiv.className = 'ai-reply';
            greetingDiv.innerHTML = `<p class="text-sm">Hello, Dr. <?php echo htmlspecialchars($sidebar_firstName); ?>. I can help you analyze the appointments in your current view. Ask me a question or use one of the suggestions below.</p>`;
            aiChatHistory.append(greetingDiv);
        }

        function toggleAIAssistant() {
            const sidebar = document.getElementById('aiAssistantSidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('aiToggleBtn');
            
            isAIAssistantVisible = !isAIAssistantVisible;
            
            if (isAIAssistantVisible) {
                // Show AI Assistant
                sidebar.classList.remove('hidden');
                setTimeout(() => {
                    sidebar.classList.remove('translate-x-full');
                }, 10);
                mainContent.classList.add('lg:pr-[374px]');
                toggleBtn.innerHTML = '<i class="fas fa-times"></i> Close AI';
                toggleBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                toggleBtn.classList.add('bg-red-600', 'hover:bg-red-700');
            } else {
                // Hide AI Assistant
                sidebar.classList.add('translate-x-full');
                setTimeout(() => {
                    sidebar.classList.add('hidden');
                }, 300);
                mainContent.classList.remove('lg:pr-[374px]');
                toggleBtn.innerHTML = '<i class="fas fa-robot"></i> AI Assistant';
                toggleBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                toggleBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }
        }

        aiAssistantForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const prompt = aiPromptInput.value.trim();
            if (!prompt) return;

            const initialGreeting = document.getElementById('ai-initial-greeting');
            if (initialGreeting) {
                initialGreeting.remove();
            }

            const userPromptDiv = document.createElement('div');
            userPromptDiv.className = 'user-prompt';
            userPromptDiv.textContent = prompt;
            aiChatHistory.append(userPromptDiv);
            aiChatHistory.scrollTop = aiChatHistory.scrollHeight;

            aiPromptInput.value = '';
            const thinkingDiv = document.createElement('div');
            thinkingDiv.className = 'ai-reply';
            thinkingDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-500"></i>';
            aiChatHistory.append(thinkingDiv);
            aiChatHistory.scrollTop = aiChatHistory.scrollHeight;

            try {
                const response = await fetch('../backend/ai_appointment_assistant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        appointments,
                        prompt
                    })
                });
                const result = await response.json();

                thinkingDiv.innerHTML = '';
                if (result.reply) {
                    thinkingDiv.textContent = result.reply;
                } else if (result.error) {
                    thinkingDiv.innerHTML = `<span class="text-red-600">${result.error}</span>`;
                    if (result.details) {
                        thinkingDiv.innerHTML += `<br><span class="text-xs text-gray-500">${typeof result.details === 'string' ? result.details : JSON.stringify(result.details)}</span>`;
                    }
                } else {
                    thinkingDiv.innerHTML = '<span class="text-red-600">AI could not generate a response.</span>';
                }
            } catch (error) {
                thinkingDiv.className = 'ai-reply';
                thinkingDiv.innerHTML = '<span class="text-red-600">An error occurred while contacting the AI assistant.</span>';
            } finally {
                aiChatHistory.scrollTop = aiChatHistory.scrollHeight;
            }
        });

        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            // Convert appointments to FullCalendar events
            const events = appointments.map(appt => {
                const datetime = appt.date + 'T' + appt.time;
                const status = appt.status.toLowerCase();
                
                return {
                    id: appt.id,
                    title: appt.patient,
                    start: datetime,
                    extendedProps: {
                        status: appt.status,
                        appointmentId: appt.id,
                        patientName: appt.patient,
                        date: appt.date,
                        time: appt.time
                    },
                    className: 'fc-event-' + status
                };
            });

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: events,
                eventClick: function(info) {
                    const appointmentId = info.event.extendedProps.appointmentId;
                    
                    if (isSelectionMode) {
                        toggleAppointmentSelection(appointmentId, info.el);
                    } else {
                        openDetailsModal(appointmentId);
                    }
                },
                dateClick: function(info) {
                    // Handle date click for creating new appointments
                    const clickedDate = info.dateStr;
                    const today = new Date().toISOString().split('T')[0];
                    
                    // Only allow future dates
                    if (clickedDate < today) {
                        showAlert('Invalid Date', 'Cannot book appointments for past dates.', 'warning');
                        return;
                    }
                    
                    openCalendarAppointmentModal(clickedDate);
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    const status = info.event.extendedProps.status;
                    const time = new Date('1970-01-01T' + info.event.extendedProps.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    info.el.title = `${info.event.title}\\nTime: ${time}\\nStatus: ${status}`;
                },
                height: 'auto',
                dayMaxEvents: 3,
                moreLinkClick: 'popover',
                eventDisplay: 'block',
                displayEventTime: true,
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short'
                },
                selectable: true,
                selectMirror: true
            });

            calendar.render();
        });

        const sidebar = document.getElementById('sidebar');
        const userDropdown = document.getElementById('user-dropdown');

        function toggleSidebar() {
            if (window.innerWidth < 1024) {
                sidebar.classList.toggle('open');
                document.getElementById('sidebar-overlay').classList.toggle('hidden');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        }

        function toggleDropdown() {
            userDropdown.classList.toggle('hidden');
        }
        window.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target) && !e.target.closest('button[onclick="toggleDropdown()"]')) {
                userDropdown.classList.add('hidden');
            }
        });

        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        async function openDetailsModal(appointmentId) {
            openModal('detailsModal');
            const contentDiv = document.getElementById('detailsModalContent');
            contentDiv.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i></div>';

            const formData = new FormData();
            formData.append('action', 'get_details');
            formData.append('appointment_id', appointmentId);

            try {
                const response = await fetch('../auth/manage_appointment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    let imagesHTML = '<p>No images attached.</p>';
                    if (data.image_paths && Array.isArray(data.image_paths) && data.image_paths.length > 0 && data.image_paths[0]) {
                        imagesHTML = data.image_paths.map(path => `<a href="../../DermaSculpt_user/${path}" target="_blank"><img src="../../DermaSculpt_user/${path}" class="w-24 h-24 object-cover rounded-md inline-block mr-2 border hover:opacity-80"></a>`).join('');
                    }

                    const displayStatus = (data.status === 'Accepted') ? 'Scheduled' : data.status;

                    // Create status badge
                    let statusBadgeClass = '';
                    switch (data.status) {
                        case 'Scheduled':
                        case 'Accepted':
                            statusBadgeClass = 'bg-green-100 text-green-800 border border-green-200';
                            break;
                        case 'Completed':
                            statusBadgeClass = 'bg-blue-100 text-blue-800 border border-blue-200';
                            break;
                        case 'Pending':
                            statusBadgeClass = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                            break;
                        case 'Cancelled':
                            statusBadgeClass = 'bg-red-100 text-red-800 border border-red-200';
                            break;
                        default:
                            statusBadgeClass = 'bg-gray-100 text-gray-800 border border-gray-200';
                    }

                    // Check if appointment date is today
                    const appointmentDate = new Date(data.appointment_date + 'T00:00:00');
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    appointmentDate.setHours(0, 0, 0, 0);
                    const isToday = appointmentDate.getTime() === today.getTime();

                    // Create action buttons based on status
                    let actionButtons = '';
                    if (data.status === 'Pending') {
                        actionButtons = `
                            <div class="flex justify-between items-center mt-4">
                                <div class="flex gap-2">
                                    <button onclick="showAppointmentConfirmation('accept', ${appointmentId}); closeModal('detailsModal');" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button onclick="showAppointmentConfirmation('cancel', ${appointmentId}); closeModal('detailsModal');" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        `;
                    } else if (data.status === 'Scheduled') {
                        const completedButtonClass = isToday 
                            ? 'bg-blue-600 text-white hover:bg-blue-700' 
                            : 'bg-gray-400 text-gray-200 cursor-not-allowed';
                        const completedButtonDisabled = isToday ? '' : 'disabled';
                        
                        actionButtons = `
                            <div class="flex justify-between items-center mt-4">
                                <div class="flex gap-2">
                                    <button onclick="openRescheduleModal(${appointmentId}, '${data.appointment_date}', '${data.appointment_time}'); closeModal('detailsModal');" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                    </button>
                                    <button onclick="showAppointmentConfirmation('cancel', ${appointmentId}); closeModal('detailsModal');" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="sendReminder(${appointmentId})" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                                        <i class="fas fa-bell"></i> Send Reminder
                                    </button>
                                    <button onclick="markCompleted(${appointmentId})" ${completedButtonDisabled} class="${completedButtonClass} px-4 py-2 rounded-lg flex items-center gap-2" title="${isToday ? 'Mark as completed' : 'Can only be completed on appointment date'}">
                                        <i class="fas fa-check-circle"></i> Mark Completed
                                    </button>
                                </div>
                            </div>
                        `;
                    } else if (data.status !== 'Cancelled' && data.status !== 'Completed') {
                        actionButtons = `
                            <div class="flex gap-2 mt-4">
                                <button onclick="openRescheduleModal(${appointmentId}, '${data.appointment_date}', '${data.appointment_time}'); closeModal('detailsModal');" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                </button>
                                <button onclick="showAppointmentConfirmation('cancel', ${appointmentId}); closeModal('detailsModal');" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        `;
                    }

                    contentDiv.innerHTML = `
                            <div class="flex justify-between items-start mb-4">
                                <h4 class="font-bold text-lg">${data.patient_name}</h4>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusBadgeClass}">${displayStatus}</span>
                            </div>
                            <p class="text-sm text-gray-500 mb-4">Contact: ${data.email || 'N/A'} | ${data.phone_number || 'N/A'}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <p><strong>Date:</strong> ${new Date(data.appointment_date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                <p><strong>Time:</strong> ${new Date('1970-01-01T' + data.appointment_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                            </div>
                            <div class="mb-4"><strong>Reason for Appointment:</strong><p class="p-3 bg-gray-50 rounded-md mt-1 border">${data.reason_for_appointment || 'None provided'}</p></div>
                            <div class="mb-4"><strong>Patient Notes:</strong><p class="p-3 bg-gray-50 rounded-md mt-1 border">${data.user_notes || 'None provided'}</p></div>
                            <div class="mb-4"><strong>Attached Images:</strong><div class="mt-2 flex flex-wrap gap-2">${imagesHTML}</div></div>
                            
                            ${actionButtons}
                        `;
                } else {
                    contentDiv.innerHTML = `<p class="text-red-500">${result.message}</p>`;
                }
            } catch (error) {
                contentDiv.innerHTML = '<p class="text-red-500">An error occurred while fetching details.</p>';
            }
        }

        // Quick Action Functions
        async function sendReminder(appointmentId) {
            showConfirmation(
                'Send Reminder',
                'Send appointment reminder to patient?',
                'Send Reminder',
                'bg-blue-600 hover:bg-blue-700',
                async () => {
                    const confirmButton = document.getElementById('confirmModalButton');
                    showButtonLoading(confirmButton, 'Send Reminder');
                    
                    try {
                        const response = await fetch('../auth/manage_appointment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=send_reminder&appointment_id=${appointmentId}`
                        });

                        const result = await response.json();
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();

                        if (result.success) {
                            showAlert('Sent!', 'Appointment reminder has been sent to the patient.', 'success');
                        } else {
                            showAlert('Error!', result.message, 'error');
                        }
                    } catch (error) {
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();
                        showAlert('Error!', 'Failed to send reminder.', 'error');
                    }
                },
                'question'
            );
        }

        async function markCompleted(appointmentId) {
            showConfirmation(
                'Mark as Completed',
                'Mark this appointment as completed?',
                'Mark Completed',
                'bg-blue-600 hover:bg-blue-700',
                async () => {
                    const confirmButton = document.getElementById('confirmModalButton');
                    showButtonLoading(confirmButton, 'Mark Completed');
                    
                    const formData = new FormData();
                    formData.append('action', 'complete');
                    formData.append('appointment_id', appointmentId);

                    try {
                        const response = await fetch('../auth/manage_appointment.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();

                        if (result.success) {
                            showAlert('Completed!', 'Appointment marked as completed.', 'success', () => location.reload());
                        } else {
                            showAlert('Error!', result.message, 'error');
                        }
                    } catch (error) {
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();
                        showAlert('Error!', 'Failed to update appointment status.', 'error');
                    }
                },
                'question'
            );
        }


        // Bulk Selection Functions
        function toggleSelectionMode() {
            isSelectionMode = !isSelectionMode;
            const toolbar = document.getElementById('bulkActionsToolbar');
            const btn = document.getElementById('selectionModeBtn');
            
            if (isSelectionMode) {
                toolbar.classList.remove('hidden');
                btn.innerHTML = '<i class="fas fa-times"></i> Disable Bulk Selection';
                btn.className = 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2';
            } else {
                toolbar.classList.add('hidden');
                btn.innerHTML = '<i class="fas fa-check-square"></i> Enable Bulk Selection';
                btn.className = 'bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2';
                clearSelection();
            }
        }

        function toggleAppointmentSelection(appointmentId, eventElement) {
            if (selectedAppointments.has(appointmentId)) {
                selectedAppointments.delete(appointmentId);
                eventElement.style.border = '';
                eventElement.style.boxShadow = '';
            } else {
                selectedAppointments.add(appointmentId);
                eventElement.style.border = '3px solid #2563eb';
                eventElement.style.boxShadow = '0 0 10px rgba(37, 99, 235, 0.5)';
            }
            updateSelectedCount();
        }

        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = selectedAppointments.size;
        }

        function clearSelection() {
            selectedAppointments.clear();
            // Remove visual selection from all events
            document.querySelectorAll('.fc-event').forEach(el => {
                el.style.border = '';
                el.style.boxShadow = '';
            });
            updateSelectedCount();
        }

        async function bulkCancel() {
            if (selectedAppointments.size === 0) {
                showAlert('No Selection', 'Please select appointments to cancel.', 'warning');
                return;
            }

            showConfirmation(
                'Cancel Appointments',
                `Are you sure you want to cancel ${selectedAppointments.size} selected appointments?`,
                'Yes, cancel them!',
                'bg-red-600 hover:bg-red-700',
                async () => {
                    const confirmButton = document.getElementById('confirmModalButton');
                    showButtonLoading(confirmButton, 'Yes, cancel them!');
                    
                    const promises = Array.from(selectedAppointments).map(appointmentId => {
                        const formData = new FormData();
                        formData.append('action', 'cancel');
                        formData.append('appointment_id', appointmentId);
                        return fetch('../auth/manage_appointment.php', {
                            method: 'POST',
                            body: formData
                        });
                    });

                    try {
                        await Promise.all(promises);
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();
                        showAlert('Success!', 'Selected appointments have been cancelled.', 'success', () => location.reload());
                    } catch (error) {
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();
                        showAlert('Error!', 'Some appointments could not be cancelled.', 'error');
                    }
                },
                'warning'
            );
        }

        async function bulkAccept() {
            if (selectedAppointments.size === 0) {
                showAlert('No Selection', 'Please select appointments to accept.', 'warning');
                return;
            }

            showConfirmation(
                'Accept Appointments',
                `Are you sure you want to accept ${selectedAppointments.size} selected appointments?`,
                'Yes, accept them!',
                'bg-green-600 hover:bg-green-700',
                async () => {
                    const confirmButton = document.getElementById('confirmModalButton');
                    showButtonLoading(confirmButton, 'Yes, accept them!');
                    
                    const promises = Array.from(selectedAppointments).map(appointmentId => {
                        const formData = new FormData();
                        formData.append('action', 'accept');
                        formData.append('appointment_id', appointmentId);
                        return fetch('../auth/manage_appointment.php', {
                            method: 'POST',
                            body: formData
                        });
                    });

                    try {
                        await Promise.all(promises);
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();
                        showAlert('Success!', 'Selected appointments have been accepted.', 'success', () => location.reload());
                    } catch (error) {
                        hideButtonLoading(confirmButton);
                        closeConfirmationModal();
                        showAlert('Error!', 'Some appointments could not be accepted.', 'error');
                    }
                },
                'question'
            );
        }

        // Manual refresh function
        async function manualRefresh() {
            const refreshBtn = document.querySelector('button[onclick="manualRefresh()"]');
            const icon = refreshBtn.querySelector('i');
            
            // Show spinning animation
            icon.classList.add('fa-spin');
            refreshBtn.disabled = true;
            
            try {
                // Force a fresh check by resetting lastUpdateTime
                lastUpdateTime = '2000-01-01 00:00:00'; // Very old timestamp to get all appointments
                await checkForUpdates();
                
                // Reset to current time for future checks
                lastUpdateTime = new Date().toISOString().slice(0, 19).replace('T', ' ');
                
                // Manual refresh completed
            } catch (error) {
                console.error('Manual refresh failed:', error);
            } finally {
                // Stop spinning animation
                icon.classList.remove('fa-spin');
                refreshBtn.disabled = false;
            }
        }

        // Real-time update functions
        async function checkForUpdates() {
            try {
                const response = await fetch(`../backend/check_updates.php?last_update=${encodeURIComponent(lastUpdateTime)}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Validate response structure
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid response format');
                }
                
                // Always update sidebar badges with the data from check_updates.php
                if (data.badge_counts) {
                    updateSidebarBadges(data.badge_counts);
                }
                
                if (data.has_updates) {
                    // Update appointment counts
                    if (data.counts) {
                        updateAppointmentCounts(data.counts);
                    }
                    
                    // Refresh calendar with new appointments
                    if (data.appointments && data.appointments.length > 0) {
                        // Update global appointments array
                        appointments.length = 0;
                        appointments.push(...data.appointments);
                        
                        // Refresh calendar
                        calendar.removeAllEvents();
                        const events = appointments.map(appt => {
                            const datetime = appt.date + 'T' + appt.time;
                            const status = appt.status.toLowerCase();
                            
                            return {
                                id: appt.id,
                                title: appt.patient,
                                start: datetime,
                                extendedProps: {
                                    status: appt.status,
                                    appointmentId: appt.id,
                                    patientName: appt.patient,
                                    date: appt.date,
                                    time: appt.time
                                },
                                className: 'fc-event-' + status
                            };
                        });
                        calendar.addEventSource(events);
                    }
                    
                    lastUpdateTime = data.last_update;
                }
            } catch (error) {
                // Silently handle errors to avoid console spam
            }
        }

        function updateAppointmentCounts(counts) {
            // Update the count displays
            const pendingCount = document.querySelector('.bg-yellow-50 .text-2xl');
            const scheduledCount = document.querySelector('.bg-green-50 .text-2xl');
            const finishedCount = document.querySelector('.bg-blue-50 .text-2xl');
            const cancelledCount = document.querySelector('.bg-red-50 .text-2xl');
            
            if (pendingCount) pendingCount.textContent = counts.pending;
            if (scheduledCount) scheduledCount.textContent = counts.scheduled;
            if (finishedCount) finishedCount.textContent = counts.finished;
            if (cancelledCount) cancelledCount.textContent = counts.cancelled;
        }

        // Update sidebar badges (same logic as sidebar.php but integrated with real-time updates)
        function updateSidebarBadges(badgeData) {
            if (!badgeData) return;
            
            // Update appointment badge
            const appointmentBadge = document.getElementById('appointmentBadge');
            if (appointmentBadge) {
                appointmentBadge.textContent = badgeData.pending_appointments;
                if (badgeData.pending_appointments > 0) {
                    appointmentBadge.classList.remove('hidden');
                    appointmentBadge.classList.add('flex');
                } else {
                    appointmentBadge.classList.add('hidden');
                    appointmentBadge.classList.remove('flex');
                }
            }
            
            // Update message badge
            const messageBadge = document.getElementById('messageBadge');
            if (messageBadge) {
                messageBadge.textContent = badgeData.unread_messages;
                if (badgeData.unread_messages > 0) {
                    messageBadge.classList.remove('hidden');
                    messageBadge.classList.add('flex');
                } else {
                    messageBadge.classList.add('hidden');
                    messageBadge.classList.remove('flex');
                }
            }
        }

        function startRealTimeUpdates() {
            // Check for updates every 3 seconds for immediate updates
            updateInterval = setInterval(checkForUpdates, 3000);
            
            // Also do an immediate check when starting
            checkForUpdates();
        }

        function stopRealTimeUpdates() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        }

        // Start real-time updates when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startRealTimeUpdates();
        });

        // Stop updates when page is hidden/minimized
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopRealTimeUpdates();
            } else {
                startRealTimeUpdates();
            }
        });

        async function openRescheduleModal(appointmentId, date, time) {
            document.getElementById('reschedule_appointment_id').value = appointmentId;
            document.getElementById('reschedule_date').value = date;
            document.getElementById('reschedule_time').value = time;
            
            // Load availability data for reschedule modal
            await loadAvailabilityData();
            
            // Set up availability checking for reschedule form
            const rescheduleDate = document.getElementById('reschedule_date');
            const rescheduleTime = document.getElementById('reschedule_time');
            
            // Add availability validation to reschedule date
            rescheduleDate.removeEventListener('input', validateRescheduleDateSelection);
            rescheduleDate.addEventListener('input', validateRescheduleDateSelection);
            
            // Add availability validation to reschedule time
            rescheduleTime.removeEventListener('input', validateRescheduleTimeSelection);
            rescheduleTime.addEventListener('input', validateRescheduleTimeSelection);
            
            openModal('rescheduleModal');
        }
        
        // Validate reschedule date selection
        function validateRescheduleDateSelection(e) {
            const selectedDate = e.target.value;
            
            // Check if selected date is a day off
            if (availabilityData.daysOff.includes(selectedDate)) {
                showAlert('Date Unavailable', 'This date is marked as a day off. Please select another date.', 'warning');
                e.target.value = '';
                return;
            }
            
            // Update available time slots for the selected date
            const rescheduleTime = document.getElementById('reschedule_time');
            const bookedTimes = availabilityData.bookedSlots[selectedDate] || [];
            rescheduleTime.dataset.bookedTimes = JSON.stringify(bookedTimes);
        }
        
        // Validate reschedule time selection
        function validateRescheduleTimeSelection(e) {
            const selectedTime = e.target.value;
            const bookedTimes = JSON.parse(e.target.dataset.bookedTimes || '[]');
            
            // Check if selected time is already booked
            if (bookedTimes.includes(selectedTime)) {
                showAlert('Time Slot Unavailable', 'This time slot is already booked. Please select another time.', 'warning');
                e.target.value = '';
                return;
            }
            
            // Validate business hours
            const hour = parseInt(selectedTime.split(':')[0]);
            const minute = parseInt(selectedTime.split(':')[1]);
            
            // Check if time is outside 7:00 AM - 9:30 PM
            if (hour < 7 || hour > 21 || (hour === 21 && minute > 30)) {
                showAlert('Invalid Time', 'Please select a time between 7:00 AM and 9:30 PM', 'warning');
                e.target.value = '';
            }
        }

        function openTransferModal(appointmentId) {
            document.getElementById('transfer_appointment_id').value = appointmentId;
            openModal('transferModal');
        }

        document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            showButtonLoading(submitButton, 'Save Changes');
            
            handleAppointmentAction('reschedule', this.elements.appointment_id.value, submitButton, {
                appointment_date: this.elements.appointment_date.value,
                appointment_time: this.elements.appointment_time.value
            });
            // Don't close modal immediately - let handleAppointmentAction close it after API call
        });

        document.getElementById('transferForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            showButtonLoading(submitButton, 'Confirm Transfer');
            
            handleAppointmentAction('transfer', this.elements.appointment_id.value, submitButton, {
                new_dermatologist_id: this.elements.new_dermatologist_id.value
            });
            // Don't close modal immediately - let handleAppointmentAction close it after API call
        });

        function showAppointmentConfirmation(action, appointmentId) {
            let title, message, confirmText, confirmColor, icon;

            if (action === 'accept') {
                title = 'Accept Appointment?';
                message = 'The patient will be notified of the confirmation.';
                confirmText = 'Yes, accept it!';
                confirmColor = 'bg-green-600 hover:bg-green-700';
                icon = 'question';
            } else if (action === 'cancel') {
                title = 'Cancel Appointment?';
                message = 'This action cannot be undone.';
                confirmText = 'Yes, cancel it!';
                confirmColor = 'bg-red-600 hover:bg-red-700';
                icon = 'warning';
            }

            showConfirmation(title, message, confirmText, confirmColor, () => {
                const confirmButton = document.getElementById('confirmModalButton');
                showButtonLoading(confirmButton, confirmText);
                handleAppointmentAction(action, appointmentId, confirmButton);
            }, icon);
        }

        async function handleAppointmentAction(action, appointmentId, buttonElement = null, extraData = {}) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('appointment_id', appointmentId);

            // Handle extraData - check if buttonElement is actually extraData (for backward compatibility)
            if (buttonElement && typeof buttonElement === 'object' && !buttonElement.tagName && !buttonElement.nodeType) {
                // buttonElement is actually extraData
                for (const key in buttonElement) {
                    formData.append(key, buttonElement[key]);
                }
                buttonElement = null;
            } else if (extraData && typeof extraData === 'object') {
                for (const key in extraData) {
                    formData.append(key, extraData[key]);
                }
            }

            try {
                const response = await fetch('../auth/manage_appointment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (buttonElement) {
                    hideButtonLoading(buttonElement);
                }

                // Close appropriate modal after API call completes
                if (action === 'reschedule') {
                    closeModal('rescheduleModal');
                } else if (action === 'transfer') {
                    closeModal('transferModal');
                } else {
                    closeConfirmationModal();
                }

                if (result.success) {
                    showAlert('Success!', result.message, 'success', () => location.reload());
                } else {
                    showAlert('Error!', result.message || 'An unknown error occurred.', 'error');
                }
            } catch (error) {
                if (buttonElement) {
                    hideButtonLoading(buttonElement);
                }
                
                // Close appropriate modal on error
                if (action === 'reschedule') {
                    closeModal('rescheduleModal');
                } else if (action === 'transfer') {
                    closeModal('transferModal');
                } else {
                    closeConfirmationModal();
                }
                
                showAlert('Error!', 'An unexpected network error occurred.', 'error');
            }
        }

        // Load availability data
        async function loadAvailabilityData() {
            try {
                const response = await fetch('../backend/get_availability.php');
                const result = await response.json();
                
                if (result.success) {
                    availabilityData = {
                        daysOff: result.daysOff || [],
                        bookedSlots: result.bookedSlots || {}
                    };
                    updateDatePickerAvailability();
                } else {
                    console.error('Failed to load availability data:', result.message);
                }
            } catch (error) {
                console.error('Error loading availability data:', error);
            }
        }

        // Update date picker to disable unavailable dates
        function updateDatePickerAvailability() {
            const dateInput = document.getElementById('appointment_date');
            
            // Remove existing event listener if any
            dateInput.removeEventListener('input', validateDateSelection);
            
            // Add new event listener
            dateInput.addEventListener('input', validateDateSelection);
            
            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
        }

        // Validate date selection
        function validateDateSelection(e) {
            const selectedDate = e.target.value;
            
            // Check if selected date is a day off
            if (availabilityData.daysOff.includes(selectedDate)) {
                showAlert('Date Unavailable', 'This date is marked as a day off. Please select another date.', 'warning');
                e.target.value = '';
                return;
            }
            
            // Update available time slots for the selected date
            updateAvailableTimeSlots(selectedDate);
        }

        // Update available time slots based on selected date
        function updateAvailableTimeSlots(selectedDate) {
            const timeInput = document.getElementById('appointment_time');
            const bookedTimes = availabilityData.bookedSlots[selectedDate] || [];
            
            // Reset time input
            timeInput.value = '';
            
            // Add event listener to validate time selection
            timeInput.removeEventListener('input', validateTimeSelection);
            timeInput.addEventListener('input', validateTimeSelection);
            
            // Store booked times for validation
            timeInput.dataset.bookedTimes = JSON.stringify(bookedTimes);
        }

        // Validate time selection
        function validateTimeSelection(e) {
            const selectedTime = e.target.value;
            const bookedTimes = JSON.parse(e.target.dataset.bookedTimes || '[]');
            
            // Check if selected time is already booked
            if (bookedTimes.includes(selectedTime)) {
                showAlert('Time Slot Unavailable', 'This time slot is already booked. Please select another time.', 'warning');
                e.target.value = '';
                return;
            }
            
            // Validate business hours
            const hour = parseInt(selectedTime.split(':')[0]);
            const minute = parseInt(selectedTime.split(':')[1]);
            
            // Check if time is outside 7:00 AM - 9:30 PM
            if (hour < 7 || hour > 21 || (hour === 21 && minute > 30)) {
                showAlert('Invalid Time', 'Please select a time between 7:00 AM and 9:30 PM', 'warning');
                e.target.value = '';
            }
        }

        // Create Appointment Modal Functions
        async function openCreateAppointmentModal() {
            document.getElementById('createAppointmentModal').classList.remove('hidden');
            
            // Load availability data
            await loadAvailabilityData();
            
            // Set default date to today (if available)
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('appointment_date');
            
            if (!availabilityData.daysOff.includes(today)) {
                dateInput.value = today;
                updateAvailableTimeSlots(today);
            }
            
            // Set default time to next available hour
            const now = new Date();
            now.setHours(now.getHours() + 1);
            now.setMinutes(0);
            const timeString = now.toTimeString().slice(0, 5);
            const timeInput = document.getElementById('appointment_time');
            
            // Only set time if it's not booked
            const bookedTimes = availabilityData.bookedSlots[today] || [];
            if (!bookedTimes.includes(timeString)) {
                timeInput.value = timeString;
            }
        }

        // Handle Create Appointment Form Submission
        document.getElementById('createAppointmentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const createBtn = document.getElementById('createAppointmentBtn');
            
            // Disable button and show loading
            createBtn.disabled = true;
            createBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';

            try {
                const response = await fetch('../backend/create_appointment.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Close modal
                    closeModal('createAppointmentModal');
                    
                    // Reset form
                    document.getElementById('createAppointmentForm').reset();
                    
                    // Show success message
                    showAlert('Success!', result.message, 'success', () => location.reload());
                } else {
                    showAlert('Error!', result.message, 'error');
                }
            } catch (error) {
                showAlert('Error!', 'Network error. Please try again.', 'error');
            } finally {
                // Re-enable button
                createBtn.disabled = false;
                createBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>Create Appointment';
            }
        });

        // Remove any phone number formatting - keep only digits
        document.getElementById('patient_phone').addEventListener('input', function(e) {
            // Allow only digits
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        // Calendar-based appointment functions
        let selectedAppointmentDate = '';
        let selectedAppointmentTime = '';

        async function openCalendarAppointmentModal(selectedDate) {
            selectedAppointmentDate = selectedDate;
            
            // Load availability data
            await loadAvailabilityData();
            
            // Check if selected date is a day off
            if (availabilityData.daysOff.includes(selectedDate)) {
                showAlert('Date Unavailable', 'This date is marked as a day off. Please select another date.', 'warning');
                return;
            }
            
            // Format and display selected date
            const formattedDate = new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            document.getElementById('selectedDateDisplay').textContent = formattedDate;
            document.getElementById('selectedDateDisplay2').textContent = formattedDate;
            
            // Generate time slots
            generateTimeSlots(selectedDate);
            
            // Show modal and reset to time selection step
            document.getElementById('timeSelectionStep').classList.remove('hidden');
            document.getElementById('patientDetailsStep').classList.add('hidden');
            document.getElementById('calendarAppointmentModal').classList.remove('hidden');
        }

        function generateTimeSlots(selectedDate) {
            const container = document.getElementById('timeSlotContainer');
            container.innerHTML = '';
            
            const bookedTimes = availabilityData.bookedSlots[selectedDate] || [];
            
            // Generate time slots from 7:00 AM to 9:30 PM (every 30 minutes)
            for (let hour = 7; hour <= 21; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    // Don't show 10:00 PM slot
                    if (hour === 22) break;
                    
                    const timeString = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                    const displayTime = new Date('1970-01-01T' + timeString).toLocaleTimeString([], {
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                    
                    const isBooked = bookedTimes.includes(timeString);
                    const isPastTime = isTimeInPast(selectedDate, timeString);
                    const isDisabled = isBooked || isPastTime;
                    
                    const timeSlot = document.createElement('button');
                    timeSlot.type = 'button';
                    timeSlot.className = `p-3 rounded-lg border-2 transition-all duration-200 text-sm font-medium ${
                        isDisabled 
                            ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed' 
                            : 'bg-white border-purple-200 text-purple-700 hover:bg-purple-50 hover:border-purple-400 hover:scale-105'
                    }`;
                    timeSlot.textContent = displayTime;
                    timeSlot.disabled = isDisabled;
                    
                    if (!isDisabled) {
                        timeSlot.onclick = () => selectTimeSlot(timeString, displayTime);
                    }
                    
                    // Add status indicator
                    if (isBooked) {
                        timeSlot.innerHTML += '<br><span class="text-xs text-red-500">Booked</span>';
                    } else if (isPastTime) {
                        timeSlot.innerHTML += '<br><span class="text-xs text-gray-400">Past</span>';
                    }
                    
                    container.appendChild(timeSlot);
                }
            }
        }

        function isTimeInPast(date, time) {
            const today = new Date().toISOString().split('T')[0];
            if (date > today) return false;
            if (date < today) return true;
            
            // Same day - check time
            const now = new Date();
            const currentTime = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            return time <= currentTime;
        }

        function selectTimeSlot(timeString, displayTime) {
            selectedAppointmentTime = timeString;
            
            // Update hidden form fields
            document.getElementById('calendar_appointment_date').value = selectedAppointmentDate;
            document.getElementById('calendar_appointment_time').value = selectedAppointmentTime;
            document.getElementById('selectedTimeDisplay').textContent = displayTime;
            
            // Move to patient details step
            document.getElementById('timeSelectionStep').classList.add('hidden');
            document.getElementById('patientDetailsStep').classList.remove('hidden');
        }

        function backToTimeSelection() {
            document.getElementById('patientDetailsStep').classList.add('hidden');
            document.getElementById('timeSelectionStep').classList.remove('hidden');
        }

        // Handle calendar appointment form submission
        document.getElementById('calendarAppointmentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const createBtn = document.getElementById('calendarCreateAppointmentBtn');
            
            // Disable button and show loading
            createBtn.disabled = true;
            createBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';

            try {
                const response = await fetch('../backend/create_appointment.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Close modal
                    closeModal('calendarAppointmentModal');
                    
                    // Reset form
                    document.getElementById('calendarAppointmentForm').reset();
                    
                    // Show success message
                    showAlert('Success!', result.message, 'success', () => location.reload());
                } else {
                    showAlert('Error!', result.message, 'error');
                }
            } catch (error) {
                showAlert('Error!', 'Network error. Please try again.', 'error');
            } finally {
                // Re-enable button
                createBtn.disabled = false;
                createBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>Create Appointment';
            }
        });

        // Remove any phone number formatting for calendar form - keep only digits
        document.getElementById('calendar_patient_phone').addEventListener('input', function(e) {
            // Allow only digits
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        // Initialize availability system on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Load availability data when page loads
            loadAvailabilityData();
        });
    </script>
</body>

</html>