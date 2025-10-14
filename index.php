<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DermaSculpt - Dermatologist Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
</head>
<style>
    body {
        overflow: hidden;
    }
</style>

<body class="bg-gray-100 font-inter">

    <nav class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <h1 class="text-2xl font-bold text-gray-800">DermaSculpt</h1>
                </div>
                <div class="hidden sm:block">
                    <p class="text-sm font-medium text-gray-600 tracking-wider">
                        Dermatology &bull; Aesthetics &bull; Lasers
                    </p>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex flex-col md:flex-row h-full min-h-screen md:h-[calc(100vh-4rem)]">
        <div class="w-1/2 bg-gray-100 flex items-center justify-center p-12">
            <div class="max-w-lg">
                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl lg:text-5xl">
                    Empower Your Practice with AI-Driven Dermatology.
                </h2>
                <p class="mt-5 text-lg sm:text-xl text-gray-500">
                    Streamline patient management, access powerful diagnostic tools, and elevate the standard of care. All in one secure platform.
                </p>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-gradient-to-br from-blue-50 to-cyan-100 flex items-center justify-center p-4 md:p-12 min-h-screen md:min-h-0">
            <div class="max-w-md w-full">
                <div class="bg-white/90 p-8 shadow-2xl rounded-3xl border border-blue-100 relative overflow-hidden">
                    <span class="absolute -top-12 -left-12 w-32 h-32 bg-cyan-200/30 rounded-full blur-2xl z-0"></span>
                    <?php session_start(); ?>
                    <div id="signup-success-alert" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 z-10" role="alert">
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline">Your account has been created. Please sign in.</span>
                    </div>
                    <?php
                    if (isset($_SESSION['login_error'])) {
                        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 z-10" role="alert">';
                        echo '<strong class="font-bold">Error!</strong>';
                        echo '<span class="block sm:inline"> ' . htmlspecialchars($_SESSION['login_error']) . '</span>';
                        echo '</div>';
                        unset($_SESSION['login_error']);
                    }
                    
                    if (isset($_SESSION['login_success'])) {
                        echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 z-10" role="alert">';
                        echo '<strong class="font-bold">Success!</strong>';
                        echo '<span class="block sm:inline"> ' . htmlspecialchars($_SESSION['login_success']) . '</span>';
                        echo '</div>';
                        unset($_SESSION['login_success']);
                    }
                    ?>
                    <div class="flex justify-center mb-6 z-10">
                        <img src="assets/dermasculpt.jpg" alt="DermaSculpt Logo" class="h-32 w-auto object-contain drop-shadow-lg rounded-2xl border-4 border-cyan-200" />
                    </div>
                    <h3 class="text-3xl font-extrabold text-cyan-700 mb-8 text-center tracking-tight z-10">Clinic Portal Login</h3>
                    <form action="auth/login_auth.php" method="POST" class="space-y-7 z-10 relative">
                        <div class="relative">
                            <input type="email" name="email" id="email" autocomplete="username" required
                                class="peer block w-full px-4 pt-6 pb-2 bg-white border border-gray-300 rounded-xl shadow-sm focus:border-cyan-500 focus:ring-cyan-500 text-base placeholder-transparent focus:outline-none transition-all duration-200"
                                placeholder="Email address" />
                            <label for="email"
                                class="absolute left-4 top-2 text-gray-500 text-sm font-medium pointer-events-none transition-all duration-200 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:top-2 peer-focus:text-sm peer-focus:text-cyan-600">
                                Email address
                            </label>
                            <span class="absolute right-4 top-4 text-cyan-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12H8m8 0a8 8 0 11-16 0 8 8 0 0116 0z" />
                                </svg>
                            </span>
                        </div>
                        <div class="relative">
                            <input type="password" name="password" id="password" autocomplete="current-password" required
                                class="peer block w-full px-4 pt-6 pb-2 bg-white border border-gray-300 rounded-xl shadow-sm focus:border-cyan-500 focus:ring-cyan-500 text-base placeholder-transparent focus:outline-none transition-all duration-200"
                                placeholder="Password" />
                            <label for="password"
                                class="absolute left-4 top-2 text-gray-500 text-sm font-medium pointer-events-none transition-all duration-200 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:top-2 peer-focus:text-sm peer-focus:text-cyan-600">
                                Password
                            </label>
                            <button type="button" id="togglePassword" tabindex="-1" class="absolute right-4 top-4 text-cyan-400 focus:outline-none">
                                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-.274.802-.63 1.566-1.058 2.272" />
                                </svg>
                                <svg id="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.042-3.362M9.88 9.88A3 3 0 0115 12m-6 0a3 3 0 016 0m0 0a3 3 0 01-3 3m0 0a3 3 0 01-3-3m0 0a3 3 0 013-3m0 0a3 3 0 013 3m0 0L4.21 4.21" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                <label for="remember-me" class="ml-2 block text-sm text-gray-900">Remember me</label>
                            </div>
                            <div class="text-sm">
                                <a href="auth/forgot_password.php" class="font-medium text-cyan-600 hover:text-cyan-700 transition-colors">Forgot your password?</a>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="w-full flex justify-center py-3 px-4 rounded-xl shadow-md text-base font-semibold text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-400 relative overflow-hidden group">
                                <span class="z-10">Sign in</span>
                                <span class="absolute inset-0 bg-cyan-100 opacity-0 group-active:opacity-40 transition-all duration-300 rounded-xl"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('signup') === 'success') {
                const alertBox = document.getElementById('signup-success-alert');
                if (alertBox) {
                    alertBox.classList.remove('hidden');
                }
                history.replaceState(null, '', window.location.pathname);
            }

            // Add loading delay logic for login form
            const loginForm = document.querySelector('form[action="auth/login_auth.php"]');
            if (loginForm) {
                const signInButton = loginForm.querySelector('button[type="submit"]');
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (signInButton) {
                        signInButton.disabled = true;
                        signInButton.textContent = 'Signing in...';
                    }
                    setTimeout(() => {
                        loginForm.submit();
                    }, 3000);
                });
            }
        });

        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        const eyeSlashIcon = document.getElementById('eye-slash-icon');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.classList.toggle('hidden');
                eyeSlashIcon.classList.toggle('hidden');
            });
        }
    </script>
</body>

</html>