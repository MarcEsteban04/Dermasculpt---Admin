<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: ../auth/login_auth.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" />
    <script>
    async function loadMetrics() {
        const from = document.getElementById('from').value;
        const to = document.getElementById('to').value;
        const params = new URLSearchParams({ from, to });
        const res = await fetch('../backend/get_reports_metrics.php?' + params.toString(), { credentials: 'include' });
        const data = await res.json();
        if (data.error) { alert('Error: ' + data.error); return; }
        document.getElementById('revTotal').textContent = '₱' + Number(data.revenue.total || 0).toFixed(2);
        document.getElementById('paidInvoices').textContent = data.revenue.paid_invoices || 0;
        document.getElementById('apptCompleted').textContent = data.appointments.completed || 0;
        document.getElementById('apptCancelled').textContent = data.appointments.cancelled || 0;
        document.getElementById('apptScheduled').textContent = data.appointments.scheduled || 0;
        document.getElementById('apptPending').textContent = data.appointments.pending || 0;

        const tbody = document.getElementById('topServices');
        tbody.innerHTML = '';
        (data.top_services || []).forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class='py-1 px-2'>${row.description}</td><td class='py-1 px-2 text-right'>₱${Number(row.total).toFixed(2)}</td>`;
            tbody.appendChild(tr);
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        const today = new Date();
        const first = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('from').value = first.toISOString().slice(0,10);
        document.getElementById('to').value = today.toISOString().slice(0,10);
        loadMetrics();
    });
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex">
        <?php include_once '../components/sidebar.php'; ?>
        <main class="ml-0 lg:ml-64 p-4 w-full">
            <h1 class="text-2xl font-bold mb-4">Reports & Analytics</h1>

            <div class="bg-white rounded shadow p-4 mb-6">
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="text-sm">From</label>
                        <input id="from" type="date" class="border rounded px-2 py-1">
                    </div>
                    <div>
                        <label class="text-sm">To</label>
                        <input id="to" type="date" class="border rounded px-2 py-1">
                    </div>
                    <button class="bg-blue-600 text-white px-3 py-1 rounded" onclick="loadMetrics()">Apply</button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded shadow p-4">
                    <div class="text-gray-500 text-sm">Revenue</div>
                    <div id="revTotal" class="text-2xl font-bold">₱0.00</div>
                </div>
                <div class="bg-white rounded shadow p-4">
                    <div class="text-gray-500 text-sm">Paid Invoices</div>
                    <div id="paidInvoices" class="text-2xl font-bold">0</div>
                </div>
                <div class="bg-white rounded shadow p-4">
                    <div class="text-gray-500 text-sm">Appointments Completed</div>
                    <div id="apptCompleted" class="text-2xl font-bold">0</div>
                </div>
                <div class="bg-white rounded shadow p-4">
                    <div class="text-gray-500 text-sm">Appointments Cancelled</div>
                    <div id="apptCancelled" class="text-2xl font-bold">0</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded shadow p-4">
                    <h2 class="text-lg font-semibold mb-2">Appointment Status</h2>
                    <div class="text-sm space-y-1">
                        <div class="flex justify-between"><span>Scheduled</span><span id="apptScheduled">0</span></div>
                        <div class="flex justify-between"><span>Pending</span><span id="apptPending">0</span></div>
                        <div class="flex justify-between"><span>Completed</span><span id="apptCompleted">0</span></div>
                        <div class="flex justify-between"><span>Cancelled</span><span id="apptCancelled">0</span></div>
                    </div>
                </div>
                <div class="bg-white rounded shadow p-4">
                    <h2 class="text-lg font-semibold mb-2">Top Services</h2>
                    <table class="w-full text-sm">
                        <thead><tr><th class="text-left px-2">Service</th><th class="text-right px-2">Revenue</th></tr></thead>
                        <tbody id="topServices"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>


