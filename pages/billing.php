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
    <title>Billing & Invoices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" />
    <script>
    async function fetchInvoices() {
        const params = new URLSearchParams();
        const status = document.getElementById('filterStatus').value;
        if (status) params.append('status', status);
        const res = await fetch('../backend/list_invoices.php?' + params.toString(), { credentials: 'include' });
        const data = await res.json();
        const tbody = document.getElementById('invoiceRows');
        tbody.innerHTML = '';
        (data.invoices || []).forEach((inv) => {
            const tr = document.createElement('tr');
            tr.className = 'border-b';
            tr.innerHTML = `
                <td class="py-2 px-3">${inv.invoice_number}</td>
                <td class="py-2 px-3">${inv.first_name} ${inv.last_name}</td>
                <td class="py-2 px-3">₱${Number(inv.total_amount).toFixed(2)}</td>
                <td class="py-2 px-3">${inv.status}</td>
                <td class="py-2 px-3">${inv.issue_date}</td>
                <td class="py-2 px-3 text-right">
                    <button class="text-blue-600 hover:underline" onclick="viewInvoice(${inv.invoice_id})">View</button>
                </td>`;
            tbody.appendChild(tr);
        });
    }

    async function viewInvoice(id) {
        const res = await fetch('../backend/get_invoice.php?invoice_id=' + id, { credentials: 'include' });
        const data = await res.json();
        const modal = document.getElementById('invoiceModal');
        const body = document.getElementById('invoiceModalBody');
        if (data.invoice) {
            const inv = data.invoice;
            const items = (data.items || []).map(it => `<tr><td class='py-1 px-2'>${it.description}</td><td class='py-1 px-2 text-right'>${Number(it.quantity).toFixed(2)}</td><td class='py-1 px-2 text-right'>₱${Number(it.unit_price).toFixed(2)}</td><td class='py-1 px-2 text-right'>₱${Number(it.line_total).toFixed(2)}</td></tr>`).join('');
            const pays = (data.payments || []).map(p => `<tr><td class='py-1 px-2'>${p.payment_method}</td><td class='py-1 px-2'>${p.reference || ''}</td><td class='py-1 px-2 text-right'>₱${Number(p.amount).toFixed(2)}</td><td class='py-1 px-2'>${p.paid_at}</td></tr>`).join('');
            body.innerHTML = `
                <div class='space-y-1'>
                    <div class='font-semibold'>${inv.invoice_number}</div>
                    <div>Patient: ${inv.first_name} ${inv.last_name}</div>
                    <div>Status: ${inv.status}</div>
                </div>
                <h3 class='mt-4 font-semibold'>Items</h3>
                <table class='w-full text-sm'><thead><tr><th class='text-left px-2'>Description</th><th class='text-right px-2'>Qty</th><th class='text-right px-2'>Unit</th><th class='text-right px-2'>Total</th></tr></thead><tbody>${items}</tbody></table>
                <div class='mt-2 text-right text-sm'>
                    <div>Subtotal: ₱${Number(inv.subtotal_amount).toFixed(2)}</div>
                    <div>Discount: ₱${Number(inv.discount_amount).toFixed(2)}</div>
                    <div>Tax: ₱${Number(inv.tax_amount).toFixed(2)}</div>
                    <div class='font-semibold'>Total: ₱${Number(inv.total_amount).toFixed(2)}</div>
                </div>
                <h3 class='mt-4 font-semibold'>Payments</h3>
                <table class='w-full text-sm'><thead><tr><th class='text-left px-2'>Method</th><th class='text-left px-2'>Ref</th><th class='text-right px-2'>Amount</th><th class='text-left px-2'>Date</th></tr></thead><tbody>${pays}</tbody></table>
                <div class='mt-3 flex gap-2'>
                    <input id='payAmount' type='number' step='0.01' placeholder='Amount' class='border rounded px-2 py-1 w-32'>
                    <select id='payMethod' class='border rounded px-2 py-1'>
                        <option value='cash'>Cash</option>
                        <option value='card'>Card</option>
                        <option value='bank'>Bank</option>
                        <option value='online'>Online</option>
                        <option value='other'>Other</option>
                    </select>
                    <input id='payRef' type='text' placeholder='Reference' class='border rounded px-2 py-1'>
                    <button class='bg-blue-600 text-white px-3 py-1 rounded' onclick='submitPayment(${inv.invoice_id})'>Record Payment</button>
                </div>
            `;
            modal.classList.remove('hidden');
        }
    }

    async function submitPayment(invoiceId) {
        const payload = {
            invoice_id: invoiceId,
            amount: parseFloat(document.getElementById('payAmount').value || '0'),
            payment_method: document.getElementById('payMethod').value,
            reference: document.getElementById('payRef').value
        };
        const res = await fetch('../backend/record_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.error) {
            alert('Payment recorded');
            document.getElementById('invoiceModal').classList.add('hidden');
            fetchInvoices();
        } else {
            alert('Error: ' + data.error);
        }
    }

    async function createInvoice(e) {
        e.preventDefault();
        const items = [];
        const rows = document.querySelectorAll('#newItems tbody tr');
        rows.forEach(r => {
            const desc = r.querySelector('.desc').value.trim();
            const qty = parseFloat(r.querySelector('.qty').value || '1');
            const unit = parseFloat(r.querySelector('.unit').value || '0');
            if (desc) items.push({ description: desc, quantity: qty, unit_price: unit });
        });
        const payload = {
            user_id: parseInt(document.getElementById('userId').value || '0'),
            appointment_id: document.getElementById('appointmentId').value ? parseInt(document.getElementById('appointmentId').value) : null,
            issue_date: document.getElementById('issueDate').value,
            due_date: document.getElementById('dueDate').value || null,
            items,
            discount_amount: parseFloat(document.getElementById('discount').value || '0'),
            tax_amount: parseFloat(document.getElementById('tax').value || '0'),
            notes: document.getElementById('notes').value
        };
        const res = await fetch('../backend/create_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.error) {
            alert('Invoice created: ' + data.invoice_number);
            (document.getElementById('newInvoiceForm')).reset();
            fetchInvoices();
        } else {
            alert('Error: ' + data.error);
        }
    }

    function addItemRow() {
        const tbody = document.querySelector('#newItems tbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><input class='desc border rounded px-2 py-1 w-full' placeholder='Description'></td>
                        <td><input type='number' step='0.01' class='qty border rounded px-2 py-1 w-24' value='1'></td>
                        <td><input type='number' step='0.01' class='unit border rounded px-2 py-1 w-28' value='0'></td>`;
        tbody.appendChild(tr);
    }

    window.addEventListener('DOMContentLoaded', () => {
        fetchInvoices();
        addItemRow();
    });
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex">
        <?php include_once '../components/sidebar.php'; ?>
        <main class="ml-0 lg:ml-64 p-4 w-full">
            <h1 class="text-2xl font-bold mb-4">Billing & Invoices</h1>

            <div class="bg-white rounded shadow p-4 mb-6">
                <div class="flex gap-2 items-end">
                    <div>
                        <label class="text-sm">Status</label>
                        <select id="filterStatus" class="border rounded px-2 py-1" onchange="fetchInvoices()">
                            <option value="">All</option>
                            <option value="sent">Sent</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                            <option value="draft">Draft</option>
                            <option value="void">Void</option>
                        </select>
                    </div>
                </div>
                <table class="w-full mt-3 text-sm">
                    <thead><tr class="text-left border-b"><th class="py-2 px-3">Invoice #</th><th class="py-2 px-3">Patient</th><th class="py-2 px-3">Total</th><th class="py-2 px-3">Status</th><th class="py-2 px-3">Issued</th><th class="py-2 px-3 text-right">Action</th></tr></thead>
                    <tbody id="invoiceRows"></tbody>
                </table>
            </div>

            <div class="bg-white rounded shadow p-4">
                <h2 class="text-xl font-semibold mb-3">Create Invoice</h2>
                <form id="newInvoiceForm" onsubmit="createInvoice(event)">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm">User ID</label>
                            <input id="userId" type="number" class="border rounded px-2 py-1 w-full" required>
                        </div>
                        <div>
                            <label class="text-sm">Appointment ID (optional)</label>
                            <input id="appointmentId" type="number" class="border rounded px-2 py-1 w-full">
                        </div>
                        <div>
                            <label class="text-sm">Issue Date</label>
                            <input id="issueDate" type="date" class="border rounded px-2 py-1 w-full" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label class="text-sm">Due Date</label>
                            <input id="dueDate" type="date" class="border rounded px-2 py-1 w-full">
                        </div>
                        <div>
                            <label class="text-sm">Discount</label>
                            <input id="discount" type="number" step="0.01" class="border rounded px-2 py-1 w-full" value="0">
                        </div>
                        <div>
                            <label class="text-sm">Tax</label>
                            <input id="tax" type="number" step="0.01" class="border rounded px-2 py-1 w-full" value="0">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm">Notes</label>
                            <textarea id="notes" class="border rounded px-2 py-1 w-full" rows="2"></textarea>
                        </div>
                    </div>
                    <h3 class="mt-4 font-semibold">Items</h3>
                    <table id="newItems" class="w-full text-sm">
                        <thead><tr><th class="text-left">Description</th><th class="text-left">Qty</th><th class="text-left">Unit Price</th></tr></thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="mt-2 text-blue-600" onclick="addItemRow()">+ Add item</button>
                    <div class="mt-4">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Create Invoice</button>
                    </div>
                </form>
            </div>

            <div id="invoiceModal" class="fixed inset-0 bg-black bg-opacity-40 hidden flex items-center justify-center">
                <div class="bg-white rounded shadow p-4 w-full max-w-2xl">
                    <div id="invoiceModalBody"></div>
                    <div class="mt-4 text-right">
                        <button class="px-4 py-2 rounded border" onclick="document.getElementById('invoiceModal').classList.add('hidden')">Close</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>


