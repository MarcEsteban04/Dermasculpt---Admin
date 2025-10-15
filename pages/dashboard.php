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

// Fetch recent AI skin analysis results
$stmt = $conn->prepare("SELECT sa.analysis_id, sa.created_at, sa.ai_diagnosis, sa.confidence_score, 
                               COALESCE(a.patient_name, sa.patient_name, 'Direct Upload') as patient_name, sa.image_path
                        FROM skin_analysis sa 
                        LEFT JOIN appointments a ON sa.appointment_id = a.appointment_id 
                        WHERE sa.dermatologist_id = ? 
                        ORDER BY sa.created_at DESC 
                        LIMIT 5");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$recentAnalysis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch recent patients (last 7 days)
$stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.profile_picture_url,
                               MAX(a.appointment_date) as last_visit,
                               COUNT(a.appointment_id) as total_appointments
                        FROM appointments a 
                        JOIN users u ON a.user_id = u.user_id 
                        WHERE a.dermatologist_id = ? AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY u.user_id, u.first_name, u.last_name, u.profile_picture_url
                        ORDER BY last_visit DESC 
                        LIMIT 6");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$recentPatients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch upcoming follow-ups and reminders
$stmt = $conn->prepare("SELECT a.appointment_id, a.patient_name, a.appointment_date, a.appointment_time,
                               DATEDIFF(a.appointment_date, CURDATE()) as days_until
                        FROM appointments a 
                        WHERE a.dermatologist_id = ? 
                        AND a.status = 'Scheduled' 
                        AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC 
                        LIMIT 5");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$upcomingFollowups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Monthly statistics
$stmt = $conn->prepare("SELECT 
                            COUNT(CASE WHEN MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE()) THEN 1 END) as this_month_appointments,
                            COUNT(CASE WHEN MONTH(appointment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(appointment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN 1 END) as last_month_appointments,
                            COUNT(CASE WHEN DATE(appointment_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days
                        FROM appointments 
                        WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$monthlyStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

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

        /* Custom animations for modal */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes modalFadeOut {
            from {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
            to {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
        }

        @keyframes successPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .modal-enter {
            animation: modalFadeIn 0.4s ease-out forwards;
        }

        .modal-exit {
            animation: modalFadeOut 0.3s ease-in forwards;
        }

        .animate-pulse-once {
            animation: successPulse 2s ease-in-out;
        }
    </style>
</head>

<body class="bg-gray-100 font-inter">
    <!-- Login Success Modal -->
    <?php if (isset($_SESSION['login_success_modal']) && $_SESSION['login_success_modal'] === true): ?>
    <div id="loginSuccessModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl p-8 mx-4 max-w-md w-full transform transition-all duration-300 scale-100 animate-pulse-once">
            <!-- Success Icon -->
            <div class="flex justify-center mb-6">
                <div class="bg-gradient-to-r from-green-400 to-emerald-500 rounded-full p-4 shadow-lg">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div class="text-center">
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Welcome Back!</h3>
                <p class="text-gray-600 mb-6">You have successfully logged into your DermaSculpt dashboard, Dr. <?php echo htmlspecialchars($firstName); ?>.</p>
                
                <!-- Features Highlight -->
                <div class="bg-gradient-to-r from-cyan-50 to-blue-50 rounded-2xl p-4 mb-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-cyan-500 rounded-full"></div>
                            <span class="text-gray-700">AI Skin Analysis</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-gray-700">Patient Management</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-purple-500 rounded-full"></div>
                            <span class="text-gray-700">Appointment Scheduling</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-orange-500 rounded-full"></div>
                            <span class="text-gray-700">Analytics Dashboard</span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Button -->
                <button onclick="closeLoginModal()" class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
                    Get Started
                </button>
            </div>
        </div>
    </div>
    <?php 
        unset($_SESSION['login_success_modal']); 
    endif; 
    ?>
    
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

            <!-- Quick Actions Panel -->
            <div class="bg-white/90 p-6 rounded-3xl shadow-2xl border border-cyan-100 mb-6">
                <h3 class="font-semibold text-cyan-700 mb-4 flex items-center">
                    <i class="fas fa-bolt mr-2"></i>Quick Actions
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                    <a href="skin_analysis.php" class="flex flex-col items-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl hover:from-purple-100 hover:to-purple-200 transition-all duration-300 group">
                        <div class="bg-purple-500 text-white rounded-full p-3 mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-microscope"></i>
                        </div>
                        <span class="text-sm font-medium text-purple-700 text-center">AI Analysis</span>
                    </a>
                    <a href="schedules.php" class="flex flex-col items-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl hover:from-green-100 hover:to-green-200 transition-all duration-300 group">
                        <div class="bg-green-500 text-white rounded-full p-3 mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <span class="text-sm font-medium text-green-700 text-center">Schedule</span>
                    </a>
                    <a href="appointments.php" class="flex flex-col items-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl hover:from-blue-100 hover:to-blue-200 transition-all duration-300 group">
                        <div class="bg-blue-500 text-white rounded-full p-3 mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-user-injured"></i>
                        </div>
                        <span class="text-sm font-medium text-blue-700 text-center">Patients</span>
                    </a>
                    <a href="appointments.php" class="flex flex-col items-center p-4 bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl hover:from-orange-100 hover:to-orange-200 transition-all duration-300 group">
                        <div class="bg-orange-500 text-white rounded-full p-3 mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                        <span class="text-sm font-medium text-orange-700 text-center">Appointments</span>
                    </a>
                    <a href="profile.php" class="flex flex-col items-center p-4 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl hover:from-indigo-100 hover:to-indigo-200 transition-all duration-300 group">
                        <div class="bg-indigo-500 text-white rounded-full p-3 mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <span class="text-sm font-medium text-indigo-700 text-center">Profile</span>
                    </a>
                    <a href="messages.php" class="flex flex-col items-center p-4 bg-gradient-to-br from-pink-50 to-pink-100 rounded-xl hover:from-pink-100 hover:to-pink-200 transition-all duration-300 group">
                        <div class="bg-pink-500 text-white rounded-full p-3 mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-comments"></i>
                        </div>
                        <span class="text-sm font-medium text-pink-700 text-center">Messages</span>
                    </a>
                </div>
            </div>

            <!-- Recent AI Analysis & Recent Patients -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Recent AI Analysis -->
                <div class="bg-white/90 p-6 rounded-3xl shadow-2xl border border-cyan-100">
                    <h3 class="font-semibold text-cyan-700 mb-4 flex items-center">
                        <i class="fas fa-brain mr-2"></i>Recent AI Analysis
                    </h3>
                    <div class="space-y-3">
                        <?php if (empty($recentAnalysis)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-microscope text-4xl mb-3 text-gray-300"></i>
                                <p>No AI analysis performed yet</p>
                                <a href="skin_analysis.php" class="inline-block mt-2 text-cyan-600 hover:text-cyan-800 font-medium">Start Analysis →</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentAnalysis as $analysis): ?>
                                <div class="flex items-center p-3 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-100">
                                    <div class="flex-shrink-0 mr-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-microscope text-purple-600"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <p class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($analysis['patient_name']); ?></p>
                                        <p class="text-sm text-purple-600 font-medium"><?php echo htmlspecialchars(substr($analysis['ai_diagnosis'], 0, 50)) . (strlen($analysis['ai_diagnosis']) > 50 ? '...' : ''); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($analysis['created_at'])); ?></p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo round($analysis['confidence_score'], 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center pt-2">
                                <a href="skin_analysis.php" class="text-cyan-600 hover:text-cyan-800 font-medium text-sm">View All Analysis →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Patients -->
                <div class="bg-white/90 p-6 rounded-3xl shadow-2xl border border-cyan-100">
                    <h3 class="font-semibold text-cyan-700 mb-4 flex items-center">
                        <i class="fas fa-users mr-2"></i>Recent Patients (Last 7 Days)
                    </h3>
                    <div class="space-y-3">
                        <?php if (empty($recentPatients)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-user-injured text-4xl mb-3 text-gray-300"></i>
                                <p>No recent patient visits</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentPatients as $patient): 
                                $patientPicture = isset($patient['profile_picture_url']) && !empty($patient['profile_picture_url']) 
                                    ? '../../DermaSculpt_user/' . htmlspecialchars($patient['profile_picture_url']) 
                                    : 'https://placehold.co/40x40/E2E8F0/4A5568?text=' . substr($patient['first_name'], 0, 1);
                            ?>
                                <div class="flex items-center p-3 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl border border-blue-100">
                                    <img class="w-10 h-10 rounded-full object-cover mr-3" src="<?php echo $patientPicture; ?>" alt="Patient">
                                    <div class="flex-grow min-w-0">
                                        <p class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                                        <p class="text-sm text-blue-600">Last visit: <?php echo date('M j, Y', strtotime($patient['last_visit'])); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $patient['total_appointments']; ?> total appointments</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <a href="appointments.php" class="text-cyan-600 hover:text-cyan-800">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center pt-2">
                                <a href="appointments.php" class="text-cyan-600 hover:text-cyan-800 font-medium text-sm">View All Patients →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
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

            <!-- Upcoming Follow-ups & Monthly Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Upcoming Follow-ups -->
                <div class="bg-white/90 p-6 rounded-3xl shadow-2xl border border-cyan-100">
                    <h3 class="font-semibold text-cyan-700 mb-4 flex items-center">
                        <i class="fas fa-clock mr-2"></i>Upcoming Follow-ups (Next 7 Days)
                    </h3>
                    <div class="space-y-3">
                        <?php if (empty($upcomingFollowups)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-calendar-check text-4xl mb-3 text-gray-300"></i>
                                <p>No upcoming follow-ups</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingFollowups as $followup): ?>
                                <div class="flex items-center p-3 bg-gradient-to-r from-orange-50 to-yellow-50 rounded-xl border border-orange-100">
                                    <div class="flex-shrink-0 mr-3">
                                        <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user-clock text-orange-600"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <p class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($followup['patient_name']); ?></p>
                                        <p class="text-sm text-orange-600"><?php echo date('M j, Y', strtotime($followup['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($followup['appointment_time'])); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php 
                                            $days = $followup['days_until'];
                                            if ($days == 0) echo 'Today';
                                            elseif ($days == 1) echo 'Tomorrow';
                                            else echo "In $days days";
                                            ?>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            <?php echo $days == 0 ? 'Today' : $days . 'd'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center pt-2">
                                <a href="schedules.php" class="text-cyan-600 hover:text-cyan-800 font-medium text-sm">View Full Schedule →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="bg-white/90 p-6 rounded-3xl shadow-2xl border border-cyan-100">
                    <h3 class="font-semibold text-cyan-700 mb-4 flex items-center">
                        <i class="fas fa-chart-bar mr-2"></i>Monthly Overview
                    </h3>
                    <div class="space-y-4">
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-green-600">This Month</p>
                                    <p class="text-2xl font-bold text-green-900"><?php echo $monthlyStats['this_month_appointments']; ?></p>
                                    <p class="text-xs text-green-700">Appointments</p>
                                </div>
                                <div class="bg-green-100 text-green-600 rounded-full p-3">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-4 rounded-xl border border-blue-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-blue-600">Last Month</p>
                                    <p class="text-2xl font-bold text-blue-900"><?php echo $monthlyStats['last_month_appointments']; ?></p>
                                    <p class="text-xs text-blue-700">Appointments</p>
                                </div>
                                <div class="bg-blue-100 text-blue-600 rounded-full p-3">
                                    <i class="fas fa-history"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-purple-50 to-indigo-50 p-4 rounded-xl border border-purple-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-purple-600">Last 30 Days</p>
                                    <p class="text-2xl font-bold text-purple-900"><?php echo $monthlyStats['last_30_days']; ?></p>
                                    <p class="text-xs text-purple-700">Total Activity</p>
                                </div>
                                <div class="bg-purple-100 text-purple-600 rounded-full p-3">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>

                        <?php 
                        $thisMonth = $monthlyStats['this_month_appointments'];
                        $lastMonth = $monthlyStats['last_month_appointments'];
                        $growth = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;
                        $growthClass = $growth >= 0 ? 'text-green-600' : 'text-red-600';
                        $growthIcon = $growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        ?>
                        <div class="text-center pt-2">
                            <p class="text-sm text-gray-600">Month-over-month growth</p>
                            <p class="font-semibold <?php echo $growthClass; ?>">
                                <i class="fas <?php echo $growthIcon; ?> mr-1"></i>
                                <?php echo abs(round($growth, 1)); ?>%
                            </p>
                        </div>
                    </div>
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

        // Login Success Modal Functions
        function closeLoginModal() {
            const modal = document.getElementById('loginSuccessModal');
            if (modal) {
                const modalContent = modal.querySelector('div');
                modalContent.classList.add('modal-exit');
                
                // Remove blur effects from background elements
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                
                if (sidebar) sidebar.style.filter = 'none';
                if (mainContent) mainContent.style.filter = 'none';
                
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }

        // Auto-close modal after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('loginSuccessModal');
            if (modal) {
                const modalContent = modal.querySelector('div');
                modalContent.classList.add('modal-enter');
                
                // Add blur effect to background elements
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                
                if (sidebar) sidebar.style.filter = 'blur(3px)';
                if (mainContent) mainContent.style.filter = 'blur(3px)';
                
                // Auto close after 5 seconds
                setTimeout(() => {
                    closeLoginModal();
                }, 5000);
            }
        });

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