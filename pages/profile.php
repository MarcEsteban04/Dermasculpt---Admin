<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];

$stmt = $conn->prepare("SELECT first_name, last_name, email, specialization, license_number, bio, profile_picture_url FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$derma = $result->fetch_assoc();
$stmt->close();

$sidebar_firstName = htmlspecialchars($derma['first_name'] ?? 'Dermatologist');
$profilePicturePath = isset($derma['profile_picture_url']) && !empty($derma['profile_picture_url']) ? '../' . htmlspecialchars($derma['profile_picture_url']) : 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - DermaSculpt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
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
    </style>
</head>

<body class="bg-gray-100 text-gray-800 font-inter">

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

        <main class="flex-1 p-4 sm:p-6 bg-gradient-to-br from-blue-50 to-cyan-100 h-[calc(100vh-4rem)] overflow-y-auto">

            <?php include '../components/alert.php'; ?>

            <div class="mb-6">
                <h2 class="text-3xl font-extrabold text-cyan-700">Profile Settings</h2>
                <p class="text-cyan-800 mt-1">Manage your personal and professional information.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1">
                    <div class="bg-white/90 p-6 rounded-2xl shadow-2xl text-center border border-cyan-100">
                        <form id="profilePictureForm" action="../auth/update_profile.php" method="POST" enctype="multipart/form-data">
                            <input type="file" name="profile_picture" id="profile_picture_input" class="hidden" accept="image/jpeg, image/png, image/gif">
                            <div class="relative w-32 h-32 mx-auto group">
                                <img src="<?php echo $profilePicturePath; ?>" id="profileImagePreview" alt="Profile Picture" class="w-32 h-32 rounded-full mx-auto object-cover ring-4 ring-cyan-200 group-hover:ring-cyan-400 transition-all duration-300">
                                <div onclick="document.getElementById('profile_picture_input').click()" class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 flex items-center justify-center rounded-full cursor-pointer transition-all duration-300">
                                    <i class="fas fa-camera text-white text-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>
                                </div>
                            </div>
                            <button type="submit" name="update_picture" id="savePictureBtn" class="hidden mt-4 bg-cyan-600 text-white py-2 px-5 rounded-lg hover:bg-cyan-700 font-semibold shadow-md transition-transform transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">Save Picture</button>
                        </form>
                        <p class="text-xs text-gray-500 mt-3 italic">To change your profile picture, hover and click the camera icon, then click "Save Picture".</p>
                        <div class="mt-4 border-t pt-4">
                            <h3 class="text-2xl font-bold text-gray-800">Dr. <?php echo htmlspecialchars($derma['first_name'] . ' ' . $derma['last_name']); ?></h3>
                            <p class="text-cyan-600 font-semibold"><?php echo htmlspecialchars($derma['specialization']); ?></p>
                            <p class="text-sm text-gray-600 mt-2">License No: <span class="font-mono"><?php echo htmlspecialchars($derma['license_number']); ?></span></p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white/90 p-6 rounded-2xl shadow-2xl border border-cyan-100">
                        <h3 class="text-xl font-semibold border-b pb-4 mb-6 text-cyan-700">Personal Information</h3>
                        <form action="../auth/update_profile.php" method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="relative">
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($derma['first_name']); ?>" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="First Name" />
                                    <label for="first_name" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">First Name</label>
                                </div>
                                <div class="relative">
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($derma['last_name']); ?>" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Last Name" />
                                    <label for="last_name" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Last Name</label>
                                </div>
                            </div>
                            <div class="relative">
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($derma['email']); ?>" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Email Address" />
                                <label for="email" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Email Address</label>
                            </div>
                            <div class="relative">
                                <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($derma['specialization']); ?>" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Specialization" />
                                <label for="specialization" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Specialization</label>
                            </div>
                            <div class="relative">
                                <textarea id="bio" name="bio" rows="4" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Biography"><?php echo htmlspecialchars($derma['bio']); ?></textarea>
                                <label for="bio" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Biography</label>
                            </div>
                            <div class="text-right">
                                <button type="submit" name="update_info" class="bg-cyan-600 text-white py-2 px-6 rounded-lg hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 font-semibold shadow-md transition-transform transform hover:scale-105">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white/90 p-6 rounded-2xl shadow-2xl border border-cyan-100">
                        <h3 class="text-xl font-semibold border-b pb-4 mb-6 text-cyan-700 flex items-center">
                            <i class="fas fa-shield-alt mr-2"></i>Change Password (OTP Protected)
                        </h3>
                        
                        <!-- Alert Messages -->
                        <div id="passwordAlert" class="hidden mb-4 p-3 rounded-lg"></div>
                        
                        <!-- Step 1: Current Password & Request OTP -->
                        <div id="step1" class="space-y-6">
                            <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                    <p class="text-sm text-blue-800">For your security, we'll send a verification code to your email before changing your password.</p>
                                </div>
                            </div>
                            
                            <form id="requestOtpForm" class="space-y-6">
                                <div class="relative">
                                    <input type="password" id="current_password_step1" name="current_password" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 pr-12 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Current Password" required>
                                    <label for="current_password_step1" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Current Password</label>
                                    <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" onclick="togglePassword('current_password_step1', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="text-right">
                                    <button type="submit" id="requestOtpBtn" class="bg-orange-600 text-white py-2 px-6 rounded-lg hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 font-semibold shadow-md transition-transform transform hover:scale-105 flex items-center">
                                        <i class="fas fa-paper-plane mr-2"></i>Send Verification Code
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Step 2: OTP Verification & New Password -->
                        <div id="step2" class="hidden space-y-6">
                            <div class="bg-green-50 border border-green-200 p-4 rounded-lg mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                    <p class="text-sm text-green-800">Verification code sent! Check your email and enter the code below along with your new password.</p>
                                </div>
                            </div>
                            
                            <form id="changePasswordForm" class="space-y-6">
                                <div class="relative">
                                    <input type="text" id="otp_code" name="otp_code" maxlength="6" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none text-center text-2xl font-mono tracking-widest" placeholder="000000" required>
                                    <label for="otp_code" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Verification Code</label>
                                </div>
                                <div class="relative">
                                    <input type="password" id="new_password_step2" name="new_password" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 pr-12 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="New Password" required>
                                    <label for="new_password_step2" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">New Password</label>
                                    <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" onclick="togglePassword('new_password_step2', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="relative">
                                    <input type="password" id="confirm_password_step2" name="confirm_password" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 pr-12 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Confirm New Password" required>
                                    <label for="confirm_password_step2" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Confirm New Password</label>
                                    <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" onclick="togglePassword('confirm_password_step2', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="flex justify-between">
                                    <button type="button" id="backToStep1" class="bg-gray-600 text-white py-2 px-6 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 font-semibold shadow-md transition-transform transform hover:scale-105">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <button type="submit" id="changePasswordBtn" class="bg-cyan-600 text-white py-2 px-6 rounded-lg hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 font-semibold shadow-md transition-transform transform hover:scale-105">
                                        <i class="fas fa-key mr-2"></i>Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const userDropdown = document.getElementById('user-dropdown');
        const profilePictureInput = document.getElementById('profile_picture_input');
        const profileImagePreview = document.getElementById('profileImagePreview');
        const savePictureBtn = document.getElementById('savePictureBtn');

        // Password visibility toggle function
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

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

        profilePictureInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImagePreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
                savePictureBtn.classList.remove('hidden');
            }
        });

        // Password Change OTP System
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const passwordAlert = document.getElementById('passwordAlert');
        const requestOtpForm = document.getElementById('requestOtpForm');
        const changePasswordForm = document.getElementById('changePasswordForm');
        const requestOtpBtn = document.getElementById('requestOtpBtn');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const backToStep1Btn = document.getElementById('backToStep1');

        function showAlert(message, type = 'error') {
            passwordAlert.className = `mb-4 p-3 rounded-lg ${type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'}`;
            passwordAlert.textContent = message;
            passwordAlert.classList.remove('hidden');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                passwordAlert.classList.add('hidden');
            }, 5000);
        }

        function hideAlert() {
            passwordAlert.classList.add('hidden');
        }

        function showStep2() {
            step1.classList.add('hidden');
            step2.classList.remove('hidden');
            hideAlert();
        }

        function showStep1() {
            step2.classList.add('hidden');
            step1.classList.remove('hidden');
            hideAlert();
            // Reset forms
            requestOtpForm.reset();
            changePasswordForm.reset();
        }

        // Handle OTP request
        requestOtpForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current_password_step1').value;
            
            if (!currentPassword) {
                showAlert('Please enter your current password');
                return;
            }

            // Disable button and show loading
            requestOtpBtn.disabled = true;
            requestOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            try {
                const formData = new FormData();
                formData.append('current_password', currentPassword);

                const response = await fetch('../auth/send_password_otp.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showStep2();
                    showAlert(result.message, 'success');
                } else {
                    showAlert(result.message);
                }
            } catch (error) {
                showAlert('Network error. Please try again.');
            } finally {
                // Re-enable button
                requestOtpBtn.disabled = false;
                requestOtpBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Verification Code';
            }
        });

        // Handle password change with OTP
        changePasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const otpCode = document.getElementById('otp_code').value;
            const newPassword = document.getElementById('new_password_step2').value;
            const confirmPassword = document.getElementById('confirm_password_step2').value;
            
            if (!otpCode || !newPassword || !confirmPassword) {
                showAlert('Please fill in all fields');
                return;
            }

            if (newPassword !== confirmPassword) {
                showAlert('New password and confirmation do not match');
                return;
            }

            if (newPassword.length < 8) {
                showAlert('Password must be at least 8 characters long');
                return;
            }

            // Disable button and show loading
            changePasswordBtn.disabled = true;
            changePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';

            try {
                const formData = new FormData();
                formData.append('otp_code', otpCode);
                formData.append('new_password', newPassword);
                formData.append('confirm_password', confirmPassword);

                const response = await fetch('../auth/verify_password_otp.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    // Reset to step 1 after successful password change
                    setTimeout(() => {
                        showStep1();
                    }, 2000);
                } else {
                    showAlert(result.message);
                }
            } catch (error) {
                showAlert('Network error. Please try again.');
            } finally {
                // Re-enable button
                changePasswordBtn.disabled = false;
                changePasswordBtn.innerHTML = '<i class="fas fa-key mr-2"></i>Update Password';
            }
        });

        // Handle back button
        backToStep1Btn.addEventListener('click', function() {
            showStep1();
        });

        // Auto-format OTP input
        document.getElementById('otp_code').addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>

</html>