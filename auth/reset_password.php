<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - DermaSculpt</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
</head>
<style>
    body {
        overflow: hidden;
    }
</style>

<body class="bg-gray-100 font-inter">
    <?php 
    session_start(); 
    
    // Redirect if no verified email in session
    if (!isset($_SESSION['verified_email'])) {
        header("location: forgot_password.php");
        exit;
    }
    ?>

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
                    Create New Password.
                </h2>
                <p class="mt-5 text-lg sm:text-xl text-gray-500">
                    Enter your new password below. Make sure it's strong and secure to protect your account.
                </p>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-gradient-to-br from-blue-50 to-cyan-100 flex items-center justify-center p-4 md:p-12 min-h-screen md:min-h-0">
            <div class="max-w-md w-full">
                <div class="bg-white/90 p-8 shadow-2xl rounded-3xl border border-blue-100 relative overflow-hidden">
                    <span class="absolute -top-12 -left-12 w-32 h-32 bg-cyan-200/30 rounded-full blur-2xl z-0"></span>
                    
                    <?php if (isset($_SESSION['reset_error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 z-10" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['reset_error']); ?></span>
                        </div>
                        <?php unset($_SESSION['reset_error']); ?>
                    <?php endif; ?>

                    <div class="flex justify-center mb-6 z-10">
                        <img src="../assets/dermasculpt.jpg" alt="DermaSculpt Logo" class="h-32 w-auto object-contain drop-shadow-lg rounded-2xl border-4 border-cyan-200" />
                    </div>
                    <h3 class="text-3xl font-extrabold text-cyan-700 mb-8 text-center tracking-tight z-10">Reset Password</h3>
                    
                    <form action="reset_password_handler.php" method="POST" class="space-y-7 z-10 relative" id="resetPasswordForm">
                        <div class="relative">
                            <input type="password" name="new_password" id="new_password" required minlength="8"
                                class="peer block w-full px-4 pt-6 pb-2 bg-white border border-gray-300 rounded-xl shadow-sm focus:border-cyan-500 focus:ring-cyan-500 text-base placeholder-transparent focus:outline-none transition-all duration-200"
                                placeholder="New Password" />
                            <label for="new_password"
                                class="absolute left-4 top-2 text-gray-500 text-sm font-medium pointer-events-none transition-all duration-200 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:top-2 peer-focus:text-sm peer-focus:text-cyan-600">
                                New Password
                            </label>
                            <button type="button" id="toggleNewPassword" tabindex="-1" class="absolute right-4 top-4 text-cyan-400 focus:outline-none">
                                <svg id="new-eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-.274.802-.63 1.566-1.058 2.272" />
                                </svg>
                                <svg id="new-eye-slash-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.042-3.362M9.88 9.88A3 3 0 0115 12m-6 0a3 3 0 016 0m0 0a3 3 0 01-3 3m0 0a3 3 0 01-3-3m0 0a3 3 0 013-3m0 0a3 3 0 013 3m0 0L4.21 4.21" />
                                </svg>
                            </button>
                        </div>

                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                                class="peer block w-full px-4 pt-6 pb-2 bg-white border border-gray-300 rounded-xl shadow-sm focus:border-cyan-500 focus:ring-cyan-500 text-base placeholder-transparent focus:outline-none transition-all duration-200"
                                placeholder="Confirm Password" />
                            <label for="confirm_password"
                                class="absolute left-4 top-2 text-gray-500 text-sm font-medium pointer-events-none transition-all duration-200 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:top-2 peer-focus:text-sm peer-focus:text-cyan-600">
                                Confirm Password
                            </label>
                            <button type="button" id="toggleConfirmPassword" tabindex="-1" class="absolute right-4 top-4 text-cyan-400 focus:outline-none">
                                <svg id="confirm-eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-.274.802-.63 1.566-1.058 2.272" />
                                </svg>
                                <svg id="confirm-eye-slash-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.042-3.362M9.88 9.88A3 3 0 0115 12m-6 0a3 3 0 016 0m0 0a3 3 0 01-3 3m0 0a3 3 0 01-3-3m0 0a3 3 0 013-3m0 0a3 3 0 013 3m0 0L4.21 4.21" />
                                </svg>
                            </button>
                        </div>

                        <!-- Password requirements -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm">
                            <h4 class="font-medium text-blue-800 mb-2">Password Requirements:</h4>
                            <ul class="text-blue-700 space-y-1">
                                <li class="flex items-center">
                                    <span id="length-check" class="mr-2">✗</span>
                                    At least 8 characters long
                                </li>
                                <li class="flex items-center">
                                    <span id="uppercase-check" class="mr-2">✗</span>
                                    At least one uppercase letter
                                </li>
                                <li class="flex items-center">
                                    <span id="lowercase-check" class="mr-2">✗</span>
                                    At least one lowercase letter
                                </li>
                                <li class="flex items-center">
                                    <span id="number-check" class="mr-2">✗</span>
                                    At least one number
                                </li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="w-full flex justify-center py-3 px-4 rounded-xl shadow-md text-base font-semibold text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-400 relative overflow-hidden group disabled:opacity-50 disabled:cursor-not-allowed" id="resetBtn" disabled>
                                <span class="z-10">Reset Password</span>
                                <span class="absolute inset-0 bg-cyan-100 opacity-0 group-active:opacity-40 transition-all duration-300 rounded-xl"></span>
                            </button>
                        </div>

                        <div class="text-center mt-6">
                            <a href="../index.php" class="font-medium text-cyan-600 hover:text-cyan-700 transition-colors">
                                ← Back to Login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const resetBtn = document.getElementById('resetBtn');
            const resetForm = document.getElementById('resetPasswordForm');
            
            // Password visibility toggles
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            
            // Password requirement checks
            const lengthCheck = document.getElementById('length-check');
            const uppercaseCheck = document.getElementById('uppercase-check');
            const lowercaseCheck = document.getElementById('lowercase-check');
            const numberCheck = document.getElementById('number-check');
            
            function checkPasswordRequirements() {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Check length
                const hasLength = password.length >= 8;
                lengthCheck.textContent = hasLength ? '✓' : '✗';
                lengthCheck.style.color = hasLength ? 'green' : 'red';
                
                // Check uppercase
                const hasUppercase = /[A-Z]/.test(password);
                uppercaseCheck.textContent = hasUppercase ? '✓' : '✗';
                uppercaseCheck.style.color = hasUppercase ? 'green' : 'red';
                
                // Check lowercase
                const hasLowercase = /[a-z]/.test(password);
                lowercaseCheck.textContent = hasLowercase ? '✓' : '✗';
                lowercaseCheck.style.color = hasLowercase ? 'green' : 'red';
                
                // Check number
                const hasNumber = /[0-9]/.test(password);
                numberCheck.textContent = hasNumber ? '✓' : '✗';
                numberCheck.style.color = hasNumber ? 'green' : 'red';
                
                // Check if passwords match
                const passwordsMatch = password === confirmPassword && password.length > 0;
                
                // Enable button if all requirements are met
                const allRequirementsMet = hasLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch;
                resetBtn.disabled = !allRequirementsMet;
                
                // Update confirm password border color
                if (confirmPassword.length > 0) {
                    confirmPasswordInput.style.borderColor = passwordsMatch ? 'green' : 'red';
                }
            }
            
            newPasswordInput.addEventListener('input', checkPasswordRequirements);
            confirmPasswordInput.addEventListener('input', checkPasswordRequirements);
            
            // Password visibility toggles
            if (toggleNewPassword) {
                toggleNewPassword.addEventListener('click', function() {
                    const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    newPasswordInput.setAttribute('type', type);
                    document.getElementById('new-eye-icon').classList.toggle('hidden');
                    document.getElementById('new-eye-slash-icon').classList.toggle('hidden');
                });
            }
            
            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    document.getElementById('confirm-eye-icon').classList.toggle('hidden');
                    document.getElementById('confirm-eye-slash-icon').classList.toggle('hidden');
                });
            }
            
            // Form submission
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (resetBtn && !resetBtn.disabled) {
                        resetBtn.disabled = true;
                        resetBtn.innerHTML = '<span class="z-10">Resetting...</span>';
                        setTimeout(() => {
                            resetForm.submit();
                        }, 1000);
                    }
                });
            }
        });
    </script>
</body>

</html>
