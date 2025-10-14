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

// Get recent analyses for the current dermatologist
$analysisStmt = $conn->prepare("
    SELECT analysis_id, patient_name, patient_age, patient_gender, image_filename, 
           ai_diagnosis, confidence_score, status, created_at, dermatologist_diagnosis
    FROM skin_analysis 
    WHERE dermatologist_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$analysisStmt->bind_param("i", $dermatologistId);
$analysisStmt->execute();
$recentAnalyses = $analysisStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$analysisStmt->close();

// Get total analysis count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM skin_analysis WHERE dermatologist_id = ?");
$countStmt->bind_param("i", $dermatologistId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalAnalyses = $countResult->fetch_assoc()['total'];
$countStmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Skin Analysis - DermaSculpt</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔬</text></svg>">
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

        .upload-area {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }

        .upload-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .analysis-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .analysis-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .image-preview {
            max-width: 300px;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
        }

        .confidence-bar {
            background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #10b981 100%);
            height: 8px;
            border-radius: 4px;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
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
                    <h2 class="text-3xl font-extrabold text-cyan-700">AI Skin Analysis</h2>
                    <p class="text-cyan-800 mt-1">Upload skin images for AI-powered diagnostic assistance with evidence-based insights.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="refreshAnalyses()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Analysis Summary -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-microscope text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-800">Total Analyses</p>
                            <p class="text-3xl font-bold text-blue-900"><?php echo $totalAnalyses; ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">AI-Powered Skin Analysis</p>
                        <p class="text-xs text-gray-500">Evidence-based diagnostic assistance</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <!-- Upload Section -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-upload text-blue-500 mr-3"></i>
                        New Analysis
                    </h3>
                    
                    <form id="analysisForm" class="space-y-4">
                        <!-- Patient Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Patient Name</label>
                                <input type="text" id="patientName" name="patient_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter patient name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                                <input type="number" id="patientAge" name="patient_age" min="1" max="120" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Age">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <select id="patientGender" name="patient_gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Image Upload -->
                        <div class="upload-area p-8 text-center rounded-lg" id="uploadArea">
                            <input type="file" id="imageInput" name="skin_image" accept="image/*" class="hidden">
                            <div id="uploadContent">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                <p class="text-lg font-medium text-gray-600 mb-2">Drop your skin image here or click to browse</p>
                                <p class="text-sm text-gray-500">Supports JPG, PNG, GIF up to 10MB</p>
                                <button type="button" id="chooseImageBtn" class="mt-4 bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                                    Choose Image
                                </button>
                            </div>
                            <div id="imagePreview" class="hidden">
                                <img id="previewImg" class="image-preview mx-auto mb-4" alt="Preview">
                                <button type="button" onclick="clearImage()" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i> Remove Image
                                </button>
                            </div>
                        </div>

                        <!-- Analysis Prompt -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Analysis Focus (Optional)</label>
                            <textarea id="analysisPrompt" name="analysis_prompt" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Describe specific areas of concern or symptoms to focus the AI analysis..."></textarea>
                        </div>

                        <button type="submit" id="analyzeBtn" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-blue-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-microscope mr-2"></i>
                            Analyze with AI
                        </button>
                    </form>
                </div>

                <!-- Recent Analyses -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-history text-green-500 mr-3"></i>
                        Recent Analyses
                    </h3>
                    
                    <div id="recentAnalyses" class="space-y-4 max-h-96 overflow-y-auto">
                        <?php if (empty($recentAnalyses)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-microscope fa-3x mb-4 opacity-50"></i>
                                <p>No analyses yet. Upload your first skin image to get started.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentAnalyses as $analysis): ?>
                                <div class="analysis-card border rounded-lg p-4 cursor-pointer hover:shadow-md" onclick="viewAnalysis(<?php echo $analysis['analysis_id']; ?>)">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($analysis['patient_name'] ?: 'Anonymous Patient'); ?>
                                            </h4>
                                            <p class="text-sm text-gray-600">
                                                <?php echo $analysis['patient_age'] ? $analysis['patient_age'] . ' years' : ''; ?>
                                                <?php echo $analysis['patient_gender'] ? ', ' . $analysis['patient_gender'] : ''; ?>
                                            </p>
                                        </div>
                                        <button onclick="event.stopPropagation(); deleteAnalysis(<?php echo $analysis['analysis_id']; ?>)" 
                                                class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-50 transition-colors"
                                                title="Delete Analysis">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                    
                                    <?php if ($analysis['confidence_score']): ?>
                                        <div class="mb-2">
                                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                                <span>Confidence</span>
                                                <span><?php echo $analysis['confidence_score']; ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="confidence-bar h-2 rounded-full" style="width: <?php echo $analysis['confidence_score']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-sm text-gray-600 mb-2">
                                        <?php echo date('M j, Y g:i A', strtotime($analysis['created_at'])); ?>
                                    </p>
                                    
                                    <?php if ($analysis['ai_diagnosis']): ?>
                                        <p class="text-sm text-gray-700 line-clamp-2">
                                            <?php echo htmlspecialchars(substr($analysis['ai_diagnosis'], 0, 100)) . '...'; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Analysis Results Modal -->
    <div id="resultsModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-xl font-semibold">Analysis Results</h3>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
                </div>
                <div id="modalContent" class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');
        const uploadContent = document.getElementById('uploadContent');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const analysisForm = document.getElementById('analysisForm');
        const analyzeBtn = document.getElementById('analyzeBtn');

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleImageSelect(files[0]);
            }
        });

        // Handle upload area click (but not on the button)
        uploadArea.addEventListener('click', (e) => {
            if (!imagePreview.classList.contains('hidden')) return;
            // Don't trigger if clicking on the button
            if (e.target.id === 'chooseImageBtn' || e.target.closest('#chooseImageBtn')) return;
            imageInput.click();
        });

        // Handle choose image button click
        document.getElementById('chooseImageBtn').addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent upload area click
            imageInput.click();
        });

        imageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImageSelect(e.target.files[0]);
            }
        });

        function handleImageSelect(file) {
            if (!file.type.startsWith('image/')) {
                Swal.fire('Error', 'Please select a valid image file.', 'error');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                Swal.fire('Error', 'Image size must be less than 10MB.', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                uploadContent.classList.add('hidden');
                imagePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }

        function clearImage() {
            imageInput.value = '';
            uploadContent.classList.remove('hidden');
            imagePreview.classList.add('hidden');
        }

        // Form submission
        analysisForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!imageInput.files[0]) {
                Swal.fire('Error', 'Please select an image to analyze.', 'error');
                return;
            }

            analyzeBtn.disabled = true;
            analyzeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Analyzing...';

            const formData = new FormData();
            formData.append('skin_image', imageInput.files[0]);
            formData.append('patient_name', document.getElementById('patientName').value);
            formData.append('patient_age', document.getElementById('patientAge').value);
            formData.append('patient_gender', document.getElementById('patientGender').value);
            formData.append('analysis_prompt', document.getElementById('analysisPrompt').value);

            try {
                const response = await fetch('../backend/skin_analysis_api.php', {
                    method: 'POST',
                    body: formData
                });

                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get response text first to check for JSON validity
                const responseText = await response.text();
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('JSON Parse Error:', jsonError);
                    console.error('Response Text:', responseText);
                    throw new Error('Server returned invalid response. Please check server logs and try again.');
                }

                if (result.success) {
                    Swal.fire({
                        title: 'Analysis Complete!',
                        text: 'The AI analysis has been completed successfully.',
                        icon: 'success',
                        confirmButtonText: 'View Results'
                    }).then(() => {
                        viewAnalysis(result.analysis_id);
                        refreshAnalyses();
                        analysisForm.reset();
                        clearImage();
                    });
                } else {
                    throw new Error(result.message || 'Analysis failed');
                }
            } catch (error) {
                console.error('Analysis error:', error);
                
                let errorMessage = 'Failed to analyze image. Please try again.';
                if (error.message.includes('JSON')) {
                    errorMessage = 'Server configuration error. Please contact support.';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                Swal.fire('Error', errorMessage, 'error');
            } finally {
                analyzeBtn.disabled = false;
                analyzeBtn.innerHTML = '<i class="fas fa-microscope mr-2"></i>Analyze with AI';
            }
        });

        async function viewAnalysis(analysisId) {
            try {
                const response = await fetch(`../backend/get_analysis.php?id=${analysisId}`);
                
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
                    document.getElementById('modalContent').innerHTML = result.html;
                    document.getElementById('resultsModal').classList.remove('hidden');
                } else {
                    throw new Error(result.message || 'Failed to load analysis');
                }
            } catch (error) {
                console.error('Load analysis error:', error);
                Swal.fire('Error', error.message || 'Failed to load analysis details.', 'error');
            }
        }

        function closeModal() {
            document.getElementById('resultsModal').classList.add('hidden');
        }

        async function deleteAnalysis(analysisId) {
            try {
                const result = await Swal.fire({
                    title: 'Delete Analysis',
                    text: 'Are you sure you want to delete this analysis? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                });

                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('analysis_id', analysisId);

                    const response = await fetch('../backend/delete_analysis.php', {
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
                        Swal.fire('Deleted!', 'The analysis has been deleted successfully.', 'success');
                        refreshAnalyses();
                    } else {
                        throw new Error(result.message || 'Failed to delete analysis');
                    }
                }
            } catch (error) {
                console.error('Delete error:', error);
                Swal.fire('Error', error.message || 'Failed to delete analysis.', 'error');
            }
        }

        async function refreshAnalyses() {
            try {
                const response = await fetch('../backend/get_recent_analyses.php');
                
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
                    document.getElementById('recentAnalyses').innerHTML = result.html;
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
