<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - DermaSculpt</title>
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
    
    // Redirect if no email in session
    if (!isset($_SESSION['reset_email'])) {
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
                    Enter Verification Code.
                </h2>
                <p class="mt-5 text-lg sm:text-xl text-gray-500">
                    We've sent a 6-digit verification code to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>. Enter the code to continue.
                </p>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-gradient-to-br from-blue-50 to-cyan-100 flex items-center justify-center p-4 md:p-12 min-h-screen md:min-h-0">
            <div class="max-w-md w-full">
                <div class="bg-white/90 p-8 shadow-2xl rounded-3xl border border-blue-100 relative overflow-hidden">
                    <span class="absolute -top-12 -left-12 w-32 h-32 bg-cyan-200/30 rounded-full blur-2xl z-0"></span>
                    
                    <?php if (isset($_SESSION['otp_error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 z-10" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['otp_error']); ?></span>
                        </div>
                        <?php unset($_SESSION['otp_error']); ?>
                    <?php endif; ?>

                    <div class="flex justify-center mb-6 z-10">
                        <img src="../assets/dermasculpt.jpg" alt="DermaSculpt Logo" class="h-32 w-auto object-contain drop-shadow-lg rounded-2xl border-4 border-cyan-200" />
                    </div>
                    <h3 class="text-3xl font-extrabold text-cyan-700 mb-8 text-center tracking-tight z-10">Verify Code</h3>
                    
                    <form action="verify_otp_handler.php" method="POST" class="space-y-7 z-10 relative" id="verifyOtpForm">
                        <div class="relative">
                            <input type="text" name="otp_code" id="otp_code" maxlength="6" pattern="[0-9]{6}" required
                                class="peer block w-full px-4 pt-6 pb-2 bg-white border border-gray-300 rounded-xl shadow-sm focus:border-cyan-500 focus:ring-cyan-500 text-base placeholder-transparent focus:outline-none transition-all duration-200 text-center text-2xl font-mono tracking-widest"
                                placeholder="000000" autocomplete="off" />
                            <label for="otp_code"
                                class="absolute left-4 top-2 text-gray-500 text-sm font-medium pointer-events-none transition-all duration-200 peer-placeholder-shown:top-4 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:top-2 peer-focus:text-sm peer-focus:text-cyan-600">
                                6-Digit Verification Code
                            </label>
                            <span class="absolute right-4 top-4 text-cyan-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="w-full flex justify-center py-3 px-4 rounded-xl shadow-md text-base font-semibold text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-400 relative overflow-hidden group" id="verifyBtn">
                                <span class="z-10">Verify Code</span>
                                <span class="absolute inset-0 bg-cyan-100 opacity-0 group-active:opacity-40 transition-all duration-300 rounded-xl"></span>
                            </button>
                        </div>

                        <div class="text-center mt-6 space-y-3">
                            <p class="text-sm text-gray-600">
                                Didn't receive the code? 
                                <a href="forgot_password.php" class="font-medium text-cyan-600 hover:text-cyan-700 transition-colors">
                                    Send again
                                </a>
                            </p>
                            <a href="../index.php" class="block font-medium text-cyan-600 hover:text-cyan-700 transition-colors">
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
            const otpInput = document.getElementById('otp_code');
            const verifyForm = document.getElementById('verifyOtpForm');
            const verifyBtn = document.getElementById('verifyBtn');
            
            // Auto-focus on OTP input
            otpInput.focus();
            
            // Only allow numbers
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            
            // Auto-submit when 6 digits are entered
            otpInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    verifyForm.submit();
                }
            });
            
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (verifyBtn) {
                        verifyBtn.disabled = true;
                        verifyBtn.innerHTML = '<span class="z-10">Verifying...</span>';
                    }
                    setTimeout(() => {
                        verifyForm.submit();
                    }, 500);
                });
            }
        });
    </script>
</body>

</html>
