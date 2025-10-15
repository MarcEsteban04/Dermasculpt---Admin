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

// Fetch data for summary cards
$stmt = $conn->prepare("SELECT COUNT(appointment_id) as count FROM appointments WHERE dermatologist_id = ? AND status = 'Scheduled'");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$upcomingCount = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM appointments WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$totalPatientsCount = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(appointment_id) as count FROM appointments WHERE dermatologist_id = ? AND appointment_date = CURDATE() AND status != 'Cancelled'");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$todayCount = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(appointment_id) as count FROM appointments WHERE dermatologist_id = ? AND status = 'Pending'");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$pendingCount = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

// Fetch data for "Today's Schedule"
$stmt = $conn->prepare("SELECT patient_name, appointment_time, status FROM appointments WHERE dermatologist_id = ? AND appointment_date = CURDATE() AND status = 'Scheduled' ORDER BY appointment_time ASC");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$todaysAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch data for "Appointment Status Distribution" doughnut chart
$stmt = $conn->prepare("SELECT status, COUNT(appointment_id) as count FROM appointments WHERE dermatologist_id = ? GROUP BY status");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$statusResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$statusData = ['Pending' => 0, 'Scheduled' => 0, 'Finished' => 0, 'Cancelled' => 0];
foreach ($statusResult as $row) {
    if (array_key_exists($row['status'], $statusData)) {
        $statusData[$row['status']] = $row['count'];
    }
}
$statusChartData = json_encode(array_values($statusData));
$statusChartLabels = json_encode(array_keys($statusData));
$stmt->close();

// Fetch data for "Weekly Appointments" bar chart
$weeklySql = "SELECT DAYNAME(appointment_date) as day, COUNT(appointment_id) as count 
               FROM appointments 
               WHERE dermatologist_id = ? AND appointment_date BETWEEN CURDATE() - INTERVAL 6 DAY AND CURDATE() 
               GROUP BY DAYOFWEEK(appointment_date), DAYNAME(appointment_date)
               ORDER BY DAYOFWEEK(appointment_date)";
$stmt = $conn->prepare($weeklySql);
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$weeklyResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$weekDays = array_reduce(
    array_map(fn($i) => date('l', strtotime("-$i day")), range(6, 0)),
    fn($acc, $day) => $acc + [$day => 0],
    []
);
foreach ($weeklyResult as $row) {
    $weekDays[$row['day']] = $row['count'];
}
$weeklyChartData = json_encode(array_values($weekDays));
$weeklyChartLabels = json_encode(array_keys($weekDays));

function getStatusClass($status)
{
    switch ($status) {
        case 'Accepted':
            return ['icon' => 'fa-check', 'bg' => 'bg-green-100', 'text' => 'text-green-600'];
        case 'Pending':
            return ['icon' => 'fa-clock', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'];
        default:
            return ['icon' => 'fa-question-circle', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DermaSculpt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
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
    </style>
</head>

<body class="bg-gray-100 font-inter">
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

        <main class="flex-1 p-4 sm:p-6 overflow-y-auto bg-gradient-to-br from-blue-50 to-cyan-100 min-h-screen">
            <div class="mb-6">
                <h2 class="text-3xl font-extrabold text-cyan-700">Welcome, Dr. <?php echo $firstName; ?>!</h2>
                <p class="text-cyan-800 mt-1">Here is a summary of your activity.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
                <div class="bg-white/80 p-6 rounded-2xl shadow-xl flex items-center justify-between border border-cyan-100">
                    <div>
                        <p class="text-sm font-medium text-cyan-600">Appointments Today</p>
                        <p class="text-3xl font-bold text-cyan-900"><?php echo $todayCount; ?></p>
                    </div>
                    <div class="bg-cyan-100 text-cyan-600 rounded-full h-12 w-12 flex items-center justify-center shadow-md border-2 border-cyan-200"><i class="fas fa-calendar-day fa-lg"></i></div>
                </div>
                <div class="bg-white/80 p-6 rounded-2xl shadow-xl flex items-center justify-between border border-cyan-100">
                    <div>
                        <p class="text-sm font-medium text-cyan-600">Upcoming Appointments</p>
                        <p class="text-3xl font-bold text-cyan-900"><?php echo $upcomingCount; ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 rounded-full h-12 w-12 flex items-center justify-center shadow-md border-2 border-green-200"><i class="fas fa-calendar-check fa-lg"></i></div>
                </div>
                <div class="bg-white/80 p-6 rounded-2xl shadow-xl flex items-center justify-between border border-cyan-100">
                    <div>
                        <p class="text-sm font-medium text-yellow-600">Pending Approvals</p>
                        <p class="text-3xl font-bold text-yellow-700"><?php echo $pendingCount; ?></p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 rounded-full h-12 w-12 flex items-center justify-center shadow-md border-2 border-yellow-200"><i class="fas fa-hourglass-half fa-lg"></i></div>
                </div>
                <div class="bg-white/80 p-6 rounded-2xl shadow-xl flex items-center justify-between border border-cyan-100">
                    <div>
                        <p class="text-sm font-medium text-indigo-600">Total Patients</p>
                        <p class="text-3xl font-bold text-indigo-900"><?php echo $totalPatientsCount; ?></p>
                    </div>
                    <div class="bg-indigo-100 text-indigo-600 rounded-full h-12 w-12 flex items-center justify-center shadow-md border-2 border-indigo-200"><i class="fas fa-users fa-lg"></i></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2 bg-white/90 p-8 rounded-3xl shadow-2xl border border-cyan-100">
                    <h3 class="font-semibold text-cyan-700 mb-4">Appointments This Week</h3>
                    <div class="h-64"><canvas id="weeklyAppointmentsChart"></canvas></div>
                </div>
                <div class="bg-white/90 p-8 rounded-3xl shadow-2xl border border-cyan-100">
                    <h3 class="font-semibold text-cyan-700 mb-4">Status Distribution</h3>
                    <div class="h-64"><canvas id="statusDoughnutChart"></canvas></div>
                </div>
            </div>

            <div class="bg-white/90 p-8 rounded-3xl shadow-2xl border border-cyan-100">
                <h3 class="font-semibold text-cyan-700 mb-4">Today's Schedule</h3>
                <div class="space-y-4">
                    <?php if (empty($todaysAppointments)): ?>
                        <p class="text-cyan-700 text-center py-4">No appointments scheduled for today.</p>
                    <?php else: ?>
                        <?php foreach ($todaysAppointments as $appt):
                            $statusInfo = getStatusClass($appt['status']);
                        ?>
                            <div class="flex items-center">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full flex items-center justify-center <?php echo $statusInfo['bg']; ?> <?php echo $statusInfo['text']; ?>">
                                    <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                                </div>
                                <div class="ml-4 flex-grow">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($appt['patient_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($appt['status']); ?></p>
                                </div>
                                <div class="text-sm font-semibold text-gray-600"><?php echo date("g:i A", strtotime($appt['appointment_time'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

        const weeklyCtx = document.getElementById('weeklyAppointmentsChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo $weeklyChartLabels; ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo $weeklyChartData; ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        const statusCtx = document.getElementById('statusDoughnutChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo $statusChartLabels; ?>,
                datasets: [{
                    label: 'Appointment Status',
                    data: <?php echo $statusChartData; ?>,
                    backgroundColor: ['rgba(251, 191, 36, 0.7)', 'rgba(52, 211, 153, 0.7)', 'rgba(99, 102, 241, 0.7)', 'rgba(239, 68, 68, 0.7)'],
                    borderColor: ['#FBBF24', '#34D399', '#6366F1', '#EF4444'],
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>

</html>