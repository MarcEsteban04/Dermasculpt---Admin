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
                        <h3 class="text-xl font-semibold border-b pb-4 mb-6 text-cyan-700">Change Password</h3>
                        <form action="../auth/update_profile.php" method="POST" class="space-y-6">
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Current Password">
                                <label for="current_password" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Current Password</label>
                            </div>
                            <div class="relative">
                                <input type="password" id="new_password" name="new_password" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="New Password">
                                <label for="new_password" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">New Password</label>
                            </div>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="peer w-full rounded-lg border border-gray-300 bg-white px-3 py-3 placeholder-transparent focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Confirm New Password">
                                <label for="confirm_password" class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-cyan-700 transition-all peer-placeholder-shown:top-3 peer-placeholder-shown:text-gray-500 peer-focus:-top-2 peer-focus:text-cyan-700">Confirm New Password</label>
                            </div>
                            <div class="text-right">
                                <button type="submit" name="change_password" class="bg-cyan-600 text-white py-2 px-6 rounded-lg hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 font-semibold shadow-md transition-transform transform hover:scale-105">Update Password</button>
                            </div>
                        </form>
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
    </script>
</body>

</html>