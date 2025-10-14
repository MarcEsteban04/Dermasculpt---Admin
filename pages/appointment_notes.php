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

// Get completed appointments for the current dermatologist
$stmt = $conn->prepare("
    SELECT appointment_id, patient_name, email, phone_number, appointment_date, appointment_time, 
           reason_for_appointment, dermatologist_notes, created_at, updated_at
    FROM appointments 
    WHERE dermatologist_id = ? AND status = 'Completed'
    ORDER BY appointment_date DESC, appointment_time DESC
");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$completedAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count completed appointments
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE dermatologist_id = ? AND status = 'Completed'");
$countStmt->bind_param("i", $dermatologistId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCompleted = $countResult->fetch_assoc()['total'];
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Notes - DermaSculpt</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📝</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        #sidebar.collapsed + .main-content {
            margin-left: var(--sidebar-width-collapsed);
            width: calc(100% - var(--sidebar-width-collapsed));
        }

        .sidebar-link.active {
            background-color: #3b82f6;
            color: white;
        }

        .appointment-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .notes-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .appointment-card {
            position: relative;
        }

        .appointment-card.has-notes {
            border-left: 4px solid #10b981;
        }

        .appointment-card.no-notes {
            border-left: 4px solid #f59e0b;
        }

        .search-container {
            position: relative;
        }

        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }

        .filter-tab.active {
            background-color: #3b82f6;
            color: white;
        }

        .filter-tab:not(.active) {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .filter-tab:not(.active):hover {
            background-color: #e5e7eb;
            color: #374151;
        }

        .appointment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .appointment-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .quick-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn.primary {
            background-color: #3b82f6;
            color: white;
        }

        .action-btn.secondary {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .notes-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .notes-indicator.has-notes {
            background-color: #10b981;
        }

        .notes-indicator.no-notes {
            background-color: #f59e0b;
        }

        .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            margin-right: 16px;
        }

        .appointment-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }

        .patient-info h3 {
            margin: 0 0 4px 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .patient-details {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.4;
        }

        .appointment-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            font-size: 0.875rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
        }

        .meta-item i {
            color: #9ca3af;
        }

        .reason-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .reason-box h4 {
            margin: 0 0 8px 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        .reason-text {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.5;
        }

        .notes-section {
            margin-bottom: 16px;
        }

        .notes-label {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 8px;
        }

        .notes-label h4 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notes-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .notes-action-btn {
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notes-action-btn.ai-generate {
            background-color: #8b5cf6;
            color: white;
        }

        .notes-action-btn.ai-improve {
            background-color: #06b6d4;
            color: white;
        }

        .notes-action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .notes-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .last-updated {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .save-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .save-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 0;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .appointment-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .appointment-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .notes-footer {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
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
                    <h2 class="text-3xl font-extrabold text-cyan-700">Appointment Notes</h2>
                    <p class="text-cyan-800 mt-1">Add clinical notes and instructions for completed appointments.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="refreshAppointments()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Enhanced Summary Section -->
            <div class="appointment-summary">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">Clinical Notes Dashboard</h3>
                        <p class="opacity-90">Manage patient follow-up instructions and clinical documentation</p>
                    </div>
                    <div class="text-right">
                        <div class="stat-number"><?php echo $totalCompleted; ?></div>
                        <div class="stat-label">Completed Appointments</div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <?php
                    $withNotes = 0;
                    $withoutNotes = 0;
                    foreach ($completedAppointments as $appointment) {
                        if (!empty($appointment['dermatologist_notes'])) {
                            $withNotes++;
                        } else {
                            $withoutNotes++;
                        }
                    }
                    ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $withNotes; ?></div>
                        <div class="stat-label">With Notes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $withoutNotes; ?></div>
                        <div class="stat-label">Need Notes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $totalCompleted > 0 ? round(($withNotes / $totalCompleted) * 100) : 0; ?>%</div>
                        <div class="stat-label">Completion Rate</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <button onclick="filterAppointments('all')" class="action-btn primary filter-tab active" data-filter="all">
                    <i class="fas fa-list"></i> All Appointments
                </button>
                <button onclick="filterAppointments('with-notes')" class="action-btn secondary filter-tab" data-filter="with-notes">
                    <i class="fas fa-check-circle"></i> With Notes
                </button>
                <button onclick="filterAppointments('without-notes')" class="action-btn secondary filter-tab" data-filter="without-notes">
                    <i class="fas fa-exclamation-circle"></i> Need Notes
                </button>
                <button onclick="markAllAsReviewed()" class="action-btn secondary">
                    <i class="fas fa-check-double"></i> Mark All Reviewed
                </button>
            </div>

            <!-- Search Bar -->
            <div class="search-container mb-6">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Search patients, dates, or reasons..." 
                           class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <button onclick="clearSearch()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Appointments Grid -->
            <div id="appointmentsContainer">
                <?php if (empty($completedAppointments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Completed Appointments</h3>
                        <p>Complete some appointments to add clinical notes and instructions.</p>
                    </div>
                <?php else: ?>
                    <div class="appointment-grid" id="appointmentGrid">
                        <?php foreach ($completedAppointments as $appointment): ?>
                            <?php 
                            $hasNotes = !empty($appointment['dermatologist_notes']);
                            $patientInitials = strtoupper(substr($appointment['patient_name'], 0, 2));
                            ?>
                            <div class="appointment-card bg-white rounded-lg shadow-lg p-6 <?php echo $hasNotes ? 'has-notes' : 'no-notes'; ?>" 
                                 data-appointment-id="<?php echo $appointment['appointment_id']; ?>"
                                 data-patient-name="<?php echo strtolower($appointment['patient_name']); ?>"
                                 data-appointment-date="<?php echo $appointment['appointment_date']; ?>"
                                 data-reason="<?php echo strtolower($appointment['reason_for_appointment'] ?? ''); ?>"
                                 data-has-notes="<?php echo $hasNotes ? 'true' : 'false'; ?>">
                                
                                <!-- Notes Indicator -->
                                <div class="notes-indicator <?php echo $hasNotes ? 'has-notes' : 'no-notes'; ?>"></div>
                                
                                <!-- Patient Header -->
                                <div class="appointment-header">
                                    <div class="patient-avatar">
                                        <?php echo $patientInitials; ?>
                                    </div>
                                    <div class="patient-info">
                                        <h3><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                        <div class="patient-details">
                                            <div class="appointment-meta">
                                                <div class="meta-item">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></span>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
                                                </div>
                                            </div>
                                            <?php if ($appointment['email']): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <span><?php echo htmlspecialchars($appointment['email']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($appointment['phone_number']): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-phone"></i>
                                                    <span><?php echo htmlspecialchars($appointment['phone_number']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reason for Appointment -->
                                <?php if ($appointment['reason_for_appointment']): ?>
                                    <div class="reason-box">
                                        <h4>Reason for Appointment</h4>
                                        <div class="reason-text"><?php echo nl2br(htmlspecialchars($appointment['reason_for_appointment'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Notes Form -->
                                <form class="notes-form" data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                    <div class="notes-section">
                                        <div class="notes-label">
                                            <h4>
                                                <i class="fas fa-stethoscope"></i>
                                                Clinical Notes & Instructions
                                            </h4>
                                        </div>
                                        
                                        <div class="notes-actions">
                                            <button type="button" onclick="generateAINotes(<?php echo $appointment['appointment_id']; ?>)" 
                                                    class="notes-action-btn ai-generate">
                                                <i class="fas fa-robot"></i>
                                                AI Generate
                                            </button>
                                            <button type="button" onclick="improveNotes(<?php echo $appointment['appointment_id']; ?>)" 
                                                    class="notes-action-btn ai-improve">
                                                <i class="fas fa-magic"></i>
                                                AI Improve
                                            </button>
                                        </div>
                                        
                                        <textarea 
                                            name="dermatologist_notes" 
                                            class="notes-textarea w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Add clinical notes, medication instructions, follow-up care, etc. (e.g., 'Take medication 3 times a day after meals', 'Apply cream twice daily', 'Follow up in 2 weeks')"
                                        ><?php echo htmlspecialchars($appointment['dermatologist_notes'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="notes-footer">
                                        <div class="last-updated">
                                            <?php if ($appointment['updated_at'] && $appointment['dermatologist_notes']): ?>
                                                Last updated: <?php echo date('M j, Y g:i A', strtotime($appointment['updated_at'])); ?>
                                            <?php else: ?>
                                                No notes added yet
                                            <?php endif; ?>
                                        </div>
                                        <button type="submit" class="save-btn">
                                            <i class="fas fa-save"></i>
                                            Save Notes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        let currentFilter = 'all';
        let allAppointments = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Store all appointments for filtering
            allAppointments = Array.from(document.querySelectorAll('.appointment-card'));
            
            // Handle form submissions
            const forms = document.querySelectorAll('.notes-form');
            forms.forEach(form => {
                form.addEventListener('submit', handleFormSubmit);
            });

            // Handle search input
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', handleSearch);

            // Initialize filter tabs
            updateFilterTabs();
        });

        // Enhanced form submission handler
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            const appointmentId = this.dataset.appointmentId;
            const notes = this.querySelector('textarea[name="dermatologist_notes"]').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const appointmentCard = this.closest('.appointment-card');
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            try {
                const formData = new FormData();
                formData.append('appointment_id', appointmentId);
                formData.append('dermatologist_notes', notes);
                
                const response = await fetch('../backend/update_appointment_notes.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('JSON Parse Error:', jsonError);
                    console.error('Response Text:', responseText);
                    throw new Error('Server returned invalid response.');
                }
                
                if (result.success) {
                    // Update the card appearance
                    const hasNotes = notes.trim().length > 0;
                    appointmentCard.className = appointmentCard.className.replace(/has-notes|no-notes/g, '');
                    appointmentCard.classList.add(hasNotes ? 'has-notes' : 'no-notes');
                    
                    // Update the indicator
                    const indicator = appointmentCard.querySelector('.notes-indicator');
                    indicator.className = indicator.className.replace(/has-notes|no-notes/g, '');
                    indicator.classList.add(hasNotes ? 'has-notes' : 'no-notes');
                    
                    // Update data attribute
                    appointmentCard.setAttribute('data-has-notes', hasNotes.toString());
                    
                    // Update last updated time
                    const lastUpdatedDiv = this.querySelector('.last-updated');
                    lastUpdatedDiv.textContent = hasNotes ? 'Last updated: ' + new Date().toLocaleString() : 'No notes added yet';
                    
                    // Show success message
                    Swal.fire({
                        title: 'Success!',
                        text: 'Notes saved successfully.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Update stats
                    updateStats();
                    
                } else {
                    throw new Error(result.message || 'Failed to save notes');
                }
            } catch (error) {
                console.error('Save error:', error);
                Swal.fire('Error', error.message || 'Failed to save notes.', 'error');
            } finally {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Notes';
            }
        }

        // Search functionality
        function handleSearch(e) {
            const searchTerm = e.target.value.toLowerCase();
            const appointments = document.querySelectorAll('.appointment-card');
            
            appointments.forEach(card => {
                const patientName = card.getAttribute('data-patient-name');
                const appointmentDate = card.getAttribute('data-appointment-date');
                const reason = card.getAttribute('data-reason');
                
                const matches = patientName.includes(searchTerm) || 
                              appointmentDate.includes(searchTerm) || 
                              reason.includes(searchTerm);
                
                if (matches) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update visible count
            updateVisibleCount();
        }

        // Clear search
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            const appointments = document.querySelectorAll('.appointment-card');
            appointments.forEach(card => {
                card.style.display = 'block';
            });
            updateVisibleCount();
        }

        // Filter appointments
        function filterAppointments(filter) {
            currentFilter = filter;
            const appointments = document.querySelectorAll('.appointment-card');
            
            appointments.forEach(card => {
                const hasNotes = card.getAttribute('data-has-notes') === 'true';
                
                let shouldShow = true;
                switch (filter) {
                    case 'with-notes':
                        shouldShow = hasNotes;
                        break;
                    case 'without-notes':
                        shouldShow = !hasNotes;
                        break;
                    case 'all':
                    default:
                        shouldShow = true;
                        break;
                }
                
                card.style.display = shouldShow ? 'block' : 'none';
            });
            
            updateFilterTabs();
            updateVisibleCount();
        }

        // Update filter tab appearance
        function updateFilterTabs() {
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active', 'primary');
                tab.classList.add('secondary');
                
                if (tab.getAttribute('data-filter') === currentFilter) {
                    tab.classList.add('active', 'primary');
                    tab.classList.remove('secondary');
                }
            });
        }

        // Update visible count
        function updateVisibleCount() {
            const visibleCards = document.querySelectorAll('.appointment-card[style*="block"], .appointment-card:not([style*="none"])');
            const visibleCount = Array.from(visibleCards).filter(card => 
                card.style.display !== 'none' && !card.style.display.includes('none')
            ).length;
            
            // Update stats in real-time
            updateStats();
        }

        // Update statistics
        function updateStats() {
            const appointments = document.querySelectorAll('.appointment-card');
            let withNotes = 0;
            let withoutNotes = 0;
            
            appointments.forEach(card => {
                const hasNotes = card.getAttribute('data-has-notes') === 'true';
                if (hasNotes) {
                    withNotes++;
                } else {
                    withoutNotes++;
                }
            });
            
            const total = withNotes + withoutNotes;
            const completionRate = total > 0 ? Math.round((withNotes / total) * 100) : 0;
            
            // Update stat cards if they exist
            const statCards = document.querySelectorAll('.stat-card');
            if (statCards.length >= 3) {
                statCards[0].querySelector('.stat-number').textContent = withNotes;
                statCards[1].querySelector('.stat-number').textContent = withoutNotes;
                statCards[2].querySelector('.stat-number').textContent = completionRate + '%';
            }
        }

        // Mark all as reviewed (placeholder function)
        function markAllAsReviewed() {
            Swal.fire({
                title: 'Mark All Reviewed',
                text: 'This feature will mark all appointments as reviewed. Continue?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark all',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Success', 'All appointments marked as reviewed!', 'success');
                }
            });
        }

        // AI Functions (placeholder implementations)
        async function generateAINotes(appointmentId) {
            Swal.fire({
                title: 'AI Generate',
                text: 'This feature will generate AI-powered clinical notes based on the appointment details.',
                icon: 'info',
                confirmButtonText: 'Coming Soon'
            });
        }

        async function improveNotes(appointmentId) {
            Swal.fire({
                title: 'AI Improve',
                text: 'This feature will improve existing notes using AI assistance.',
                icon: 'info',
                confirmButtonText: 'Coming Soon'
            });
        }

        async function refreshAppointments() {
            try {
                const response = await fetch('../backend/get_completed_appointments.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const responseText = await response.text();
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('JSON Parse Error in refresh:', jsonError);
                    console.error('Response Text:', responseText);
                    return; // Silently fail for refresh
                }

                if (result.success) {
                    // Reload the page to show updated data
                    window.location.reload();
                }
            } catch (error) {
                console.error('Refresh error:', error);
                // Silently fail for refresh - don't show error to user
            }
        }

        // Sidebar functionality
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
    </script>
</body>

</html>
