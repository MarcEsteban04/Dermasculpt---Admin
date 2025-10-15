<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$firstName = htmlspecialchars($_SESSION['first_name'] ?? 'Dermatologist');

$stmt = $conn->prepare("SELECT first_name, last_name, email, specialization, license_number, bio, profile_picture_url FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$derma = $result->fetch_assoc();

$sidebar_firstName = htmlspecialchars($derma['first_name'] ?? 'Dermatologist');
$profilePicturePath = isset($derma['profile_picture_url']) && !empty($derma['profile_picture_url']) ? '../' . htmlspecialchars($derma['profile_picture_url']) : 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';

$stmt = $conn->prepare("SELECT day_off_id, off_date, reason FROM dermatologist_day_off WHERE dermatologist_id = ? AND off_date >= CURDATE() ORDER BY off_date ASC");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$daysOffResult = $stmt->get_result();
$daysOff = $daysOffResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules & Day Off - DermaSculpt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
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

        #sidebar.collapsed .sidebar-link i {
            margin-right: 0;
        }

        #sidebar.collapsed .sidebar-link {
            justify-content: center;
        }

        #sidebar.collapsed .profile-avatar {
            margin: auto;
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

        @media (max-width: 1024px) {
            #sidebar {
                position: fixed;
                left: -100%;
                z-index: 50;
                transition: left 0.3s ease-in-out;
            }

            #sidebar.open {
                left: 0;
            }

            #sidebar.collapsed {
                width: var(--sidebar-width);
            }

            #sidebar.collapsed .sidebar-text,
            #sidebar.collapsed .sidebar-logo-text,
            #sidebar.collapsed .profile-info {
                display: block;
            }

            #sidebar.collapsed .sidebar-link i {
                margin-right: 1rem;
            }

            #sidebar.collapsed .sidebar-link {
                justify-content: flex-start;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            #sidebar.collapsed+.main-content {
                margin-left: 0;
                width: 100%;
            }

            #sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
        }

        .sidebar-link.active {
            background-color: #3b82f6;
            color: white;
        }

        .form-input-container {
            position: relative;
        }

        .form-input-container .form-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .form-input {
            padding-left: 2.5rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
            outline: none;
        }

        /* Add these new styles for the AI sidebar */
        #aiSidebar {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            transition: right 0.3s ease-in-out;
            z-index: 40;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
        }

        #aiSidebar.open {
            right: 0;
        }

        .ai-trigger-btn {
            position: fixed;
            right: 2rem;
            bottom: 2rem;
            z-index: 39;
            transition: right 0.3s ease-in-out;
        }

        #aiSidebar.open + .ai-trigger-btn {
            right: 420px;
        }

        .ai-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 35;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        .ai-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* Add these styles for AI tooltip */
        .ai-tooltip {
            position: fixed;
            right: 5rem;
            bottom: 5rem;
            padding: 1rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 300px;
            z-index: 38;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

    <?php include '../components/sidebar.php'; ?>
    <div id="sidebar-overlay" class="hidden lg:hidden" onclick="toggleSidebar()"></div>

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

        <main class="flex-1 p-4 sm:p-6 bg-gradient-to-br from-blue-50 to-cyan-100 h-[calc(100vh-4rem)] overflow-y-auto">
            <div class="mb-6">
                <h2 class="text-3xl font-extrabold text-cyan-700">Schedules & Days Off</h2>
                <p class="text-cyan-800 mt-1">Manage your weekly availability and set specific days off.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-white/90 p-6 rounded-2xl shadow-2xl border border-cyan-100">
                        <h3 class="text-xl font-semibold border-b border-cyan-100 pb-4 mb-6 flex items-center text-cyan-700">
                            <i class="fas fa-calendar-plus mr-3 text-cyan-500"></i>
                            Add a Day Off
                        </h3>
                        <form action="../auth/manage_schedule.php" method="POST" class="space-y-5">
                            <div class="relative">
                                <input type="date" id="off_date" name="off_date" min="<?php echo date('Y-m-d'); ?>" required
                                       class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none">
                                <label for="off_date" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700">Date</label>
                            </div>
                            <div class="relative">
                                <input type="text" id="reason" name="reason" placeholder="e.g., Personal Holiday"
                                       class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none">
                                <label for="reason" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700">Reason (Optional)</label>
                            </div>
                            <div class="pt-2">
                                <button type="submit" name="add_day_off" class="w-full bg-cyan-600 text-white py-2 px-6 rounded-lg hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 font-semibold shadow-md transition-transform transform hover:scale-105">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Day Off
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-3">
                    <div class="bg-white/90 rounded-2xl shadow-2xl border border-cyan-100">
                        <div class="p-6 border-b border-cyan-100">
                            <h3 class="text-xl font-semibold text-cyan-700 flex items-center">
                                <i class="fas fa-calendar-check mr-3 text-cyan-500"></i>
                                Upcoming Days Off
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-cyan-100">
                                <thead class="bg-cyan-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-cyan-700 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-cyan-700 uppercase tracking-wider">Reason</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-cyan-700 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="days-off-tbody" class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($daysOff)): ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-16 text-center">
                                                <div class="flex flex-col items-center">
                                                    <i class="fas fa-inbox fa-3x text-gray-300"></i>
                                                    <p class="mt-4 font-medium text-gray-600">No upcoming days off found.</p>
                                                    <p class="text-gray-400">Your schedule is clear!</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($daysOff as $day): 
                                            $dayDate = strtotime($day['off_date']);
                                            $today = strtotime(date('Y-m-d'));
                                            
                                            if ($dayDate < $today) {
                                                $status = 'Ended';
                                                $statusClass = 'bg-gray-100 text-gray-600';
                                            } elseif ($dayDate > $today) {
                                                $status = 'Pending';
                                                $statusClass = 'bg-yellow-100 text-yellow-600';
                                            } else {
                                                $status = 'Active';
                                                $statusClass = 'bg-green-100 text-green-600';
                                            }
                                        ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800">
                                                    <?php echo date("l, F j, Y", strtotime($day['off_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                                    <?php echo htmlspecialchars($day['reason'] ?: 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <form action="../auth/manage_schedule.php" method="POST" class="delete-form">
                                                        <input type="hidden" name="day_off_id" value="<?php echo $day['day_off_id']; ?>">
                                                        <button type="submit" name="delete_day_off" class="text-red-500 hover:text-red-700 transition-colors p-2 rounded-full hover:bg-red-100" title="Delete">
                                                            <i class="fas fa-trash-alt fa-fw"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Assistant Floating Button -->
            <button onclick="toggleAISidebar()" class="ai-trigger-btn bg-cyan-600 text-white p-4 rounded-full shadow-lg hover:bg-cyan-700 transition-all duration-300 group">
                <i class="fas fa-robot text-2xl group-hover:scale-110 transition-transform"></i>
            </button>

            <!-- AI Sidebar -->
            <div id="aiSidebar" class="bg-white/95 backdrop-blur-sm">
                <div class="h-full flex flex-col">
                    <div class="p-6 border-b border-cyan-100">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold text-cyan-700 flex items-center">
                                <i class="fas fa-robot mr-3 text-cyan-500"></i>
                                AI Schedule Suggestions
                            </h3>
                            <button onclick="toggleAISidebar()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 p-6 overflow-y-auto">
                        <div class="space-y-4">
                            <button onclick="getAIAnalysis()" class="w-full bg-gradient-to-r from-cyan-600 to-cyan-700 text-white py-2 px-4 rounded-lg hover:from-cyan-700 hover:to-cyan-800 transition-all duration-300 flex items-center justify-center gap-2">
                                <i class="fas fa-magic"></i>
                                Get Schedule Suggestions
                            </button>
                        </div>
                        <div id="aiAnalysisResult" class="mt-4 space-y-4 hidden">
                            <div class="animate-pulse" id="loadingState">
                                <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                            </div>
                            <div id="analysisContent" class="hidden">
                                <div class="text-gray-700 mb-4" id="workloadAnalysis"></div>
                                <div class="space-y-3" id="suggestions">
                                    <!-- Suggestions will be inserted here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Sidebar Overlay -->
            <div class="ai-overlay" onclick="toggleAISidebar()"></div>

            <!-- AI Assistant Tooltip -->
            <div id="aiTooltip" class="ai-tooltip hidden">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="text-cyan-600 text-xl">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Meet Your AI Schedule Assistant!</h4>
                            <p class="text-sm text-gray-600 mt-1">Get smart suggestions for managing your days off based on your appointment patterns.</p>
                        </div>
                    </div>
                    <button onclick="dismissTooltip()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mt-4 flex gap-2">
                    <button onclick="tryAIAssistant()" class="flex-1 bg-cyan-600 text-white py-2 px-4 rounded-lg hover:bg-cyan-700 text-sm">Try it now</button>
                    <button onclick="dismissTooltip()" class="flex-1 bg-gray-100 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-200 text-sm">Maybe later</button>
                </div>
            </div>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const userDropdown = document.getElementById('user-dropdown');

        function toggleSidebar() {
            if (window.innerWidth < 1024) {
                document.getElementById('sidebar').classList.toggle('open');
                document.getElementById('sidebar-overlay').classList.toggle('hidden');
            } else {
                document.getElementById('sidebar').classList.toggle('collapsed');
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

        document.getElementById('days-off-tbody').addEventListener('click', function(event) {
            const deleteButton = event.target.closest('button[name="delete_day_off"]');

            if (deleteButton) {
                event.preventDefault();
                const form = deleteButton.closest('.delete-form');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        });

        function getAIAnalysis() {
            const resultDiv = document.getElementById('aiAnalysisResult');
            const loadingState = document.getElementById('loadingState');
            const analysisContent = document.getElementById('analysisContent');
            const workloadAnalysis = document.getElementById('workloadAnalysis');
            const suggestionsDiv = document.getElementById('suggestions');
            
            resultDiv.classList.remove('hidden');
            loadingState.classList.remove('hidden');
            analysisContent.classList.add('hidden');
            
            fetch('../backend/ai_schedule_analysis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingState.classList.add('hidden');
                analysisContent.classList.remove('hidden');

                workloadAnalysis.textContent = data.analysis;

                // Defensive: Only map if suggestions is an array
                if (Array.isArray(data.suggestions) && data.suggestions.length > 0) {
                    suggestionsDiv.innerHTML = data.suggestions.map(suggestion => `
                        <div class="bg-cyan-50 p-4 rounded-lg border border-cyan-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-cyan-800">${formatDate(suggestion.date)}</div>
                                    <div class="text-sm text-cyan-600">${suggestion.reason}</div>
                                </div>
                                <button onclick="quickAddDayOff('${suggestion.date}')" 
                                        class="bg-cyan-600 text-white px-4 py-2 rounded hover:bg-cyan-700 transition-colors duration-200 flex items-center gap-2">
                                    <i class="fas fa-plus"></i>
                                    Add
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    suggestionsDiv.innerHTML = `<div class="text-gray-500 italic">No suggestions available.</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="text-red-500">
                        <i class="fas fa-exclamation-circle"></i>
                        Error getting AI analysis. Please try again.
                    </div>
                `;
                console.error('Error:', error);
            });
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function quickAddDayOff(date) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../auth/manage_schedule.php';
            
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'off_date';
            dateInput.value = date;
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'add_day_off';
            submitInput.value = '1';
            
            form.appendChild(dateInput);
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Add this new function for toggling the AI sidebar
        function toggleAISidebar() {
            const sidebar = document.getElementById('aiSidebar');
            const overlay = document.querySelector('.ai-overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }

        // Add these new functions for AI tooltip
        function checkAndShowTooltip() {
            setTimeout(() => {
                document.getElementById('aiTooltip').classList.remove('hidden');
            }, 1500);
        }

        function dismissTooltip() {
            document.getElementById('aiTooltip').classList.add('hidden');
        }

        function tryAIAssistant() {
            dismissTooltip();
            toggleAISidebar();
        }

        // Call on page load - this will now show every time
        document.addEventListener('DOMContentLoaded', checkAndShowTooltip);
    </script>
</body>

</html>