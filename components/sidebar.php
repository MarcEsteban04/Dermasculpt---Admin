<?php
// This component assumes a session has already been started.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Include the database connection configuration
require_once __DIR__ . '/../config/connection.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$unreadMessageCount = 0;
$pendingAppointmentCount = 0;

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] && isset($_SESSION['dermatologist_id'])) {
    $dermatologistId = $_SESSION['dermatologist_id'];

    // Query to count unread messages for the current dermatologist
    $sql = "SELECT COUNT(message_id) FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_role = 'dermatologist' AND sender_role = 'user'";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $dermatologistId);
        $stmt->execute();
        $stmt->bind_result($unreadMessageCount);
        $stmt->fetch();
        $stmt->close();
    }

    // Query to count pending appointments for the current dermatologist
    $appointmentSql = "SELECT COUNT(appointment_id) FROM appointments WHERE dermatologist_id = ? AND status = 'Pending'";

    if ($appointmentStmt = $conn->prepare($appointmentSql)) {
        $appointmentStmt->bind_param("i", $dermatologistId);
        $appointmentStmt->execute();
        $appointmentStmt->bind_result($pendingAppointmentCount);
        $appointmentStmt->fetch();
        $appointmentStmt->close();
    }

    $sidebar_firstName = htmlspecialchars($_SESSION['first_name'] ?? 'Dermatologist');
    $sidebar_profilePictureUrl = $_SESSION['profile_picture_url'] ?? 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';
} else {
    $sidebar_firstName = 'Dermatologist';
    $sidebar_profilePictureUrl = 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';
}

$profilePicturePath = (!empty($sidebar_profilePictureUrl) && $sidebar_profilePictureUrl !== 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr') ? '../' . htmlspecialchars($sidebar_profilePictureUrl) : $sidebar_profilePictureUrl;

?>

<div id="sidebar-overlay" class="hidden lg:hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="bg-gradient-to-br from-blue-50 to-cyan-100 h-full fixed top-0 left-0 flex flex-col shadow-2xl z-50 rounded-r-3xl border-r border-blue-100">
    <div class="flex items-center justify-center p-6 border-b h-16 flex-shrink-0 bg-white/90 rounded-tr-3xl shadow-md">
        <i class="fa-solid fa-stethoscope text-blue-600 text-3xl"></i>
        <h1 class="sidebar-logo-text text-2xl font-bold text-gray-800 ml-3">DermaSculpt</h1>
    </div>

    <nav class="flex-1 mt-6 px-4 space-y-2">
        <a href="dashboard.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'dashboard.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-tachometer-alt w-6 text-center mr-4"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>
        <a href="appointments.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'appointments.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-calendar-alt w-6 text-center mr-4"></i>
            <span class="sidebar-text">Appointments</span>
            <?php if ($pendingAppointmentCount > 0): ?>
                <span id="appointmentBadge" class="ml-auto bg-orange-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] flex items-center justify-center px-1.5">
                    <?php echo $pendingAppointmentCount; ?>
                </span>
            <?php else: ?>
                <span id="appointmentBadge" class="ml-auto bg-orange-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] items-center justify-center px-1.5 hidden">
                    0
                </span>
            <?php endif; ?>
        </a>
        <a href="messages.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'messages.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-comments w-6 text-center mr-4"></i>
            <span class="sidebar-text">Messages</span>
            <?php if ($unreadMessageCount > 0): ?>
                <span id="messageBadge" class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] flex items-center justify-center px-1.5">
                    <?php echo $unreadMessageCount; ?>
                </span>
            <?php else: ?>
                <span id="messageBadge" class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] items-center justify-center px-1.5 hidden">
                    0
                </span>
            <?php endif; ?>
        </a>
        <a href="skin_analysis.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'skin_analysis.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-microscope w-6 text-center mr-4"></i>
            <span class="sidebar-text">Skin Analysis</span>
        </a>
        <a href="appointment_notes.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'appointment_notes.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-clipboard-list w-6 text-center mr-4"></i>
            <span class="sidebar-text">Appointment Notes</span>
        </a>
        <a href="schedules.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'schedules.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-calendar-times w-6 text-center mr-4"></i>
            <span class="sidebar-text">Schedules</span>
        </a>
        <a href="profile.php" class="sidebar-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 font-bold shadow-sm <?php echo ($currentPage == 'profile.php') ? 'bg-cyan-600 text-white shadow-lg' : 'text-gray-700 hover:bg-cyan-100 hover:text-cyan-700'; ?>">
            <i class="fas fa-user-cog w-6 text-center mr-4"></i>
            <span class="sidebar-text">Settings</span>
        </a>
    </nav>

    <div class="p-4 border-t bg-white/90 rounded-br-3xl shadow-inner">
        <a href="profile.php" class="flex items-center w-full">
            <img class="profile-avatar h-12 w-12 rounded-full object-cover border-4 border-cyan-200 shadow-md" src="<?php echo $profilePicturePath; ?>" alt="Profile picture">
            <div class="profile-info ml-3">
                <p class="text-sm font-semibold text-gray-800 sidebar-text">Dr. <?php echo $sidebar_firstName; ?></p>
                <p class="text-xs text-gray-500 sidebar-text">View Profile</p>
            </div>
        </a>
    </div>
</aside>

<div id="logoutConfirmModal" class="fixed inset-0 z-[999] hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md border border-gray-100">
            <div class="p-5 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Confirm Logout</h3>
                <p class="text-sm text-gray-600 mt-1">Are you sure you want to log out?</p>
            </div>
            <div class="p-5 flex items-center justify-end gap-3">
                <button id="logoutCancelBtn" class="px-4 py-2 rounded-lg border hover:bg-gray-50 text-gray-700">Cancel</button>
                <button id="logoutConfirmBtn" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white">Logout</button>
            </div>
        </div>
    </div>
    </div>

<script src="../js/sidebar-badges.js"></script>
<script>
(function() {
    let pendingLogoutHref = null;
    const modal = document.getElementById('logoutConfirmModal');
    const cancelBtn = document.getElementById('logoutCancelBtn');
    const confirmBtn = document.getElementById('logoutConfirmBtn');

    function openModal(href) {
        pendingLogoutHref = href;
        modal.classList.remove('hidden');
    }
    function closeModal() {
        pendingLogoutHref = null;
        modal.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const anchor = e.target.closest('a[href]');
        if (!anchor) return;
        const href = anchor.getAttribute('href') || '';
        // Match any logout link regardless of relative depth
        if (href.endsWith('auth/logout.php')) {
            e.preventDefault();
            openModal(anchor.href);
        }
    });

    cancelBtn?.addEventListener('click', function() { closeModal(); });
    // Also close when clicking backdrop
    modal?.addEventListener('click', function(e) {
        if (e.target === modal || e.target.classList.contains('bg-opacity-50')) {
            closeModal();
        }
    });
    confirmBtn?.addEventListener('click', function() {
        if (pendingLogoutHref) {
            window.location.href = pendingLogoutHref;
        }
    });
})();
</script>
