<?php
// Set default timezone to Tbilisi
date_default_timezone_set('Asia/Tbilisi');
// Get current date in format required for datetime-local input (YYYY-MM-DDTHH:MM)
$currentDate = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Shop Manager - Invoice</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: { 'fade-in': 'fadeIn 0.3s ease-in-out' },
                    keyframes: { fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } } }
                }
            }
        }
    </script>
    <style>
        /* Print Styles */
        @media print {
            @page { margin: 0; size: A4; }
            html, body { height: 100%; margin: 0 !important; padding: 0 !important; overflow: hidden; }
            .print-hidden { display: none !important; }
            .print-visible { display: block !important; }
            .print-no-shadow { box-shadow: none !important; }
            /* Exact A4 Table Styling */
            table { border-collapse: collapse !important; border-color: #000 !important; width: 100% !important; }
            td, th { border: 1px solid #000 !important; color: #000 !important; }
            
            /* Compact padding for print to fit one page */
            td { padding-top: 2px !important; padding-bottom: 2px !important; padding-left: 4px !important; padding-right: 4px !important; }
            
            /* Ensure container is exact A4 height */
            .a4-container { height: 297mm !important; max-height: 297mm !important; overflow: hidden !important; }
        }
        
        .tab-active { background-color: #eab308; color: #0f172a; font-weight: bold; }
        .tab-inactive { background-color: transparent; color: white; }
        .tab-inactive:hover { background-color: #334155; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 pb-20 md:pb-0">

    <!-- NAVIGATION -->
    <nav class="bg-slate-800 text-white p-4 shadow-md print-hidden sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4 md:gap-0">
            <div class="flex items-center gap-2">
                <!-- Car Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" />
                    <circle cx="7" cy="17" r="2" />
                    <circle cx="17" cy="17" r="2" />
                </svg>
                <span class="text-xl font-bold">AutoShop PHP</span>
            </div>
            <div class="flex w-full md:w-auto gap-2 overflow-x-auto pb-1 md:pb-0">
                <button onclick="switchTab('edit')" id="btn-edit" class="flex-1 md:flex-none whitespace-nowrap px-4 py-2 rounded-md transition-colors tab-active">Edit Details</button>
                <button onclick="switchTab('preview')" id="btn-preview" class="flex-1 md:flex-none whitespace-nowrap px-4 py-2 rounded-md transition-colors tab-inactive">Preview Invoice</button>
                <button onclick="handlePrint()" class="flex-1 md:flex-none whitespace-nowrap px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-500 flex items-center justify-center gap-2 font-semibold shadow-sm active:scale-95 transition-all text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-4 md:p-8 print:p-0 print:max-w-none">
        
        <!-- ================= EDIT MODE ================= -->
        <div id="edit-mode" class="block print-hidden animate-fade-in">
            <form id="invoice-form" onsubmit="return false;">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                    
                    <!-- Left Column: Inputs -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Workflow Card -->
                        <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm border-t-4 border-yellow-500">
                            <h2 class="text-lg md:text-xl font-bold mb-4 flex items-center gap-2 text-slate-700">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Workflow Details
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-1">Creation Date (შემოსვლის დრო)</label>
                                    <input type="datetime-local" id="input_creation_date" name="creation_date" value="<?php echo $currentDate; ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-3 border text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-1">Service Manager (სერვისის მენეჯერი)</label>
                                    <input type="text" id="input_service_manager" placeholder="Manager Name" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-3 border text-base">
                                </div>
                            </div>
                        </div>

                        <!-- Customer Card -->
                        <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm border-t-4 border-blue-500">
                            <h2 class="text-lg md:text-xl font-bold mb-4 flex items-center gap-2 text-slate-700">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                Customer Info
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-1">Customer Name (კლიენტი)</label>
                                    <input type="text" id="input_customer_name" placeholder="First Last Name" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3 border text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-1">Phone Number (ტელეფონი)</label>
                                    <input type="tel" id="input_phone_number" placeholder="+995 555 00 00 00" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3 border text-base">
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Card -->
                        <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm border-t-4 border-red-500">
                            <h2 class="text-lg md:text-xl font-bold mb-4 flex items-center gap-2 text-slate-700">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                Vehicle Data
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-1">Car Mark/Model (ავტომანქანა)</label>
                                    <input type="text" id="input_car_mark" placeholder="e.g., Toyota Camry" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 border text-base">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Plate Number (ნომერი)</label>
                                        <input type="text" id="input_plate_number" placeholder="ZZ-000-ZZ" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 border text-base">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Mileage (გარბენი)</label>
                                        <input type="text" id="input_mileage" placeholder="150000 km" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 border text-base">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Line Items -->
                    <div class="lg:col-span-2">
                        <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm h-full flex flex-col">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg md:text-xl font-bold text-slate-700">Service & Parts</h2>
                                <button type="button" onclick="addItemRow()" class="flex items-center gap-1 text-sm bg-green-100 text-green-700 px-4 py-2 rounded-full hover:bg-green-200 transition-colors font-medium active:scale-95">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    Add Row
                                </button>
                            </div>

                            <div class="overflow-x-auto -mx-4 md:mx-0 px-4 md:px-0 flex-grow">
                                <table class="w-full text-sm text-left min-w-[600px]">
                                    <thead class="bg-gray-50 text-gray-600 uppercase">
                                        <tr>
                                            <th class="px-3 py-3 w-8">#</th>
                                            <th class="px-3 py-3 w-1/3">Item Name</th>
                                            <th class="px-3 py-3 w-16">Qty</th>
                                            <th class="px-3 py-3 w-24">Part Price</th>
                                            <th class="px-3 py-3 w-24">Svc Price</th>
                                            <th class="px-3 py-3 w-32">Technician</th>
                                            <th class="px-3 py-3 w-12 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-table-body" class="divide-y divide-gray-100">
                                        <!-- Rows added via JS -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Live Totals -->
                            <div class="mt-6 border-t pt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-right">
                                <div class="bg-gray-50 p-3 rounded flex justify-between md:block">
                                    <p class="text-xs text-gray-500 uppercase">Parts Total</p>
                                    <p class="font-bold text-lg" id="display_parts_total">0.00</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded flex justify-between md:block">
                                    <p class="text-xs text-gray-500 uppercase">Service Total</p>
                                    <p class="font-bold text-lg" id="display_service_total">0.00</p>
                                </div>
                                <div class="bg-green-50 p-3 rounded border border-green-100 flex justify-between md:block">
                                    <p class="text-xs text-green-600 uppercase">Grand Total</p>
                                    <p class="font-bold text-xl text-green-700" id="display_grand_total">0.00 ₾</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ================= PREVIEW / PRINT MODE ================= -->
        <div id="preview-mode" class="hidden print-visible flex-col items-center">
            <div class="mb-4 print-hidden text-gray-500 text-sm flex items-center gap-2">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Click the <strong>Print</strong> button above to download as PDF or print.</span>
            </div>

            <!-- A4 Container -->
            <div class="w-full overflow-x-auto pb-8 print:pb-0 print:overflow-visible flex justify-center bg-gray-200/50 p-4 rounded-lg print:bg-white print:p-0">
                <div class="bg-white p-8 shadow-xl print-no-shadow w-[210mm] min-w-[210mm] min-h-[297mm] a4-container print:w-full print:max-w-none print:min-w-0 print:p-0 mx-auto box-border text-black relative">
                    
                    <!-- Header -->
                    <div class="grid grid-cols-2 mb-6 gap-8 items-start">
                        <div class="text-sm space-y-1">
                            <!-- Hardcoded Logo Placeholder -->
                            <!-- You can replace this SVG with an <img> tag pointing to your logo URL -->
                            <div class="mb-2 text-slate-800">
<img src="https://service.otoexpress.ge/wp-content/uploads/2023/08/cropped-otomotors.png" width="50%"></img>
                            </div>
                            
                            <p class="font-bold text-lg">ს.ს. თიბისი ბანკი</p>
                            <p>ბანკის კოდი: <span class="font-mono">TBCBGE22</span></p>
                            <p>ა/ნ: <span class="font-mono">GE64TB7669336080100009</span></p>
                        </div>
                        <div class="text-sm space-y-1 text-right">
                            <p class="font-bold text-lg">შპს "ოტო მოტორს ჰოლდინგი"</p>
                            <p>ს/კ: <span class="font-mono">406239887</span></p>
                            <p>მის: აღმაშენებლის ხეივანი მე-13 კმ.</p>
                        </div>
                    </div>

                    <hr class="border-2 border-black mb-4" />

                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 text-sm">
                        <!-- Left -->
                        <div class="grid grid-cols-[150px_1fr] gap-2 items-center">
                            <div class="font-bold whitespace-nowrap">შემოსვლის დრო:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center" id="out_creation_date"></div>

                            <div class="font-bold whitespace-nowrap">კლიენტი:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center font-bold" id="out_customer_name"></div>

                            <div class="font-bold whitespace-nowrap">ავტომანქანა:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center" id="out_car_mark"></div>

                            <div class="font-bold whitespace-nowrap">ა/მ სახ. #:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center font-mono uppercase" id="out_plate_number"></div>
                        </div>

                        <!-- Right -->
                        <div class="grid grid-cols-[200px_1fr] gap-2 items-center">
                            <div class="font-bold whitespace-nowrap">სერვისის დაწყების დრო:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center"></div>

                            <div class="font-bold whitespace-nowrap">ტელ:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center font-mono" id="out_phone_number"></div>

                            <div class="font-bold whitespace-nowrap">გარბენი:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center" id="out_mileage"></div>

                            <div class="font-bold whitespace-nowrap">სერვისის მენეჯერი:</div>
                            <div class="border-b border-black px-2 h-6 flex items-center" id="out_service_manager"></div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="mb-4">
                        <table class="w-full text-xs border-collapse border border-black">
                            <thead>
                                <tr class="bg-gray-200 print:bg-gray-200">
                                    <th class="border border-black p-1 w-8 text-center">#</th>
                                    <th class="border border-black p-1 text-left">ნაწილის და სერვისის დასახელება</th>
                                    <th class="border border-black p-1 w-12 text-center">რაოდ.</th>
                                    <th class="border border-black p-1 w-20 text-right">ფასი ნაწილი</th>
                                    <th class="border border-black p-1 w-20 text-right">თანხა</th>
                                    <th class="border border-black p-1 w-20 text-right">ფასი სერვისი</th>
                                    <th class="border border-black p-1 w-20 text-right">თანხა</th>
                                    <th class="border border-black p-1 w-24 text-left">შემსრულებელი</th>
                                </tr>
                            </thead>
                            <tbody id="preview-table-body">
                                <!-- JS will populate this -->
                            </tbody>
                        </table>

                        <!-- Grand Total -->
                        <div class="flex justify-end mt-2">
                            <div class="border border-black px-4 py-2 bg-yellow-100 print:bg-yellow-100 text-lg font-bold">
                                სულ გადასახდელი: <span id="out_grand_total">0.00</span> ₾
                            </div>
                        </div>
                    </div>

                    <!-- Legal Text -->
                    <div class="text-[9px] text-gray-600 space-y-2 mb-8 text-justify leading-tight">
                        <p><strong>შენიშვნა:</strong> კლიენტის მიერ მოწოდებული ნაწილის ხარისხზე და გამართულობაზე კომპანია არ აგებს პასუხს. მანქანის შეკეთებისას თუ კლიენტი გადაწყვეტს ნაწილის მოწოდებას, ვალდებულია ნაწილი მოაწოდოს სერვისს არაუგვიანეს 2 სამუშაო დღისა, წინააღმდეგ შემთხვევაში მანქანა გადაინაცვლებს კომპანიის ავტოსადგომზე, რა შემთხვევაშიც მანქანის დგომის დღიური საფასური იქნება 10 ლარი. თუ შენიშვნის ველში გარანტიის ვადა არ არის მითითებული გარანტია არ ვრცელდება. წინამდებარე დოკუმენტზე ხელმოწერით კლიენტი ადასტურებს რომ კომპანიის მიმართ პრეტენზია არ გააჩნია.</p>
                        <p><strong>საგარანტიო პირობები:</strong> 1. აალების სანთლების საგარანტიო ვადა განისაზღვრება კილომეტრაჟით, რომელიც შეადგენს 1000 კმ-ს. 2. სამუხრუჭე ხუნდების საგარანტიო ვადა განისაზღვრება მონტაჟიდან 7 დღის ვადით.</p>
                        <p class="italic mt-4">Oneclub: საიდან გაიგეთ ჩვენს შესახებ? ________________________</p>
                    </div>

                    <!-- Signatures -->
                    <div class="grid grid-cols-2 gap-20 mt-8 text-sm absolute bottom-12 w-full left-0 px-8 box-border">
                        <div class="border-t border-black pt-2 text-center">მენეჯერის ხელმოწერა</div>
                        <div class="border-t border-black pt-2 text-center">კლიენტის ხელმოწერა</div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- JS Logic -->
    <script>
        // Store items state
        let rowCount = 0;

        // Initialize with 4 rows
        document.addEventListener('DOMContentLoaded', () => {
            for(let i=0; i<4; i++) addItemRow();
            calculateTotals();
        });

        function addItemRow() {
            rowCount++;
            const tbody = document.getElementById('items-table-body');
            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 item-row";
            tr.id = `row-${rowCount}`;
            tr.innerHTML = `
                <td class="px-3 py-3 font-medium text-center text-gray-400 row-number"></td>
                <td class="px-3 py-3"><input type="text" placeholder="Description" class="item-name w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="number" min="1" value="1" oninput="calculateTotals()" class="item-qty w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="number" min="0" value="0" oninput="calculateTotals()" class="item-price-part w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="number" min="0" value="0" oninput="calculateTotals()" class="item-price-svc w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="text" placeholder="Name" class="item-tech w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3 text-center">
                    <button onclick="removeRow(${rowCount})" class="text-red-400 hover:text-red-600 p-2 rounded-full hover:bg-red-50 transition-colors">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            renumberRows();
        }

        function removeRow(id) {
            const row = document.getElementById(`row-${id}`);
            if(row) {
                row.remove();
                renumberRows();
                calculateTotals();
            }
        }

        function renumberRows() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach((row, index) => {
                row.querySelector('.row-number').innerText = index + 1;
            });
        }

        function calculateTotals() {
            let partTotal = 0;
            let svcTotal = 0;
            
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const pPart = parseFloat(row.querySelector('.item-price-part').value) || 0;
                const pSvc = parseFloat(row.querySelector('.item-price-svc').value) || 0;
                
                partTotal += (qty * pPart);
                svcTotal += (qty * pSvc);
            });

            document.getElementById('display_parts_total').innerText = partTotal.toFixed(2);
            document.getElementById('display_service_total').innerText = svcTotal.toFixed(2);
            document.getElementById('display_grand_total').innerText = (partTotal + svcTotal).toFixed(2) + ' ₾';

            return { partTotal, svcTotal, grandTotal: partTotal + svcTotal };
        }

        function switchTab(tab) {
            const editMode = document.getElementById('edit-mode');
            const previewMode = document.getElementById('preview-mode');
            const btnEdit = document.getElementById('btn-edit');
            const btnPreview = document.getElementById('btn-preview');

            if (tab === 'edit') {
                editMode.classList.remove('hidden');
                previewMode.classList.add('hidden');
                previewMode.classList.remove('flex');
                
                btnEdit.className = "flex-1 md:flex-none whitespace-nowrap px-4 py-2 rounded-md transition-colors tab-active";
                btnPreview.className = "flex-1 md:flex-none whitespace-nowrap px-4 py-2 rounded-md transition-colors tab-inactive";
            } else {
                updatePreviewData();
                editMode.classList.add('hidden');
                previewMode.classList.remove('hidden');
                previewMode.classList.add('flex');

                btnEdit.className = "flex-1 md:flex-none whitespace-nowrap px-4 py-2 rounded-md transition-colors tab-inactive";
                btnPreview.className = "flex-1 md:flex-none whitespace-nowrap px-4 py-2 rounded-md transition-colors tab-active";
            }
        }

        function updatePreviewData() {
            // Map Inputs
            const map = {
                'input_creation_date': 'out_creation_date',
                'input_customer_name': 'out_customer_name',
                'input_car_mark': 'out_car_mark',
                'input_plate_number': 'out_plate_number',
                'input_phone_number': 'out_phone_number',
                'input_mileage': 'out_mileage',
                'input_service_manager': 'out_service_manager'
            };

            for(const [inId, outId] of Object.entries(map)) {
                let val = document.getElementById(inId).value;
                if(inId === 'input_creation_date') val = val.replace('T', ' ');
                document.getElementById(outId).innerText = val;
            }

            // Build Table
            const totals = calculateTotals();
            const tbody = document.getElementById('preview-table-body');
            tbody.innerHTML = ''; // Clear

            const rows = document.querySelectorAll('.item-row');
            let index = 1;

            const formatPrice = (val) => val > 0 ? val.toFixed(2) : "";

            rows.forEach(row => {
                const name = row.querySelector('.item-name').value;
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const pPart = parseFloat(row.querySelector('.item-price-part').value) || 0;
                const pSvc = parseFloat(row.querySelector('.item-price-svc').value) || 0;
                const tech = row.querySelector('.item-tech').value;

                // Do not display zeros in rows (if empty name or 0 value)
                if (!name && pPart === 0 && pSvc === 0) {
                     // If name is empty and values are 0, assume it's an empty/filler row from edit mode
                     // We will still render it to maintain the row count visual from edit mode, but blank.
                }

                const totalPart = qty * pPart;
                const totalSvc = qty * pSvc;

                // Logic: If Name is empty, show blank row (even if default values exist)
                // If Name exists, show values, but if specific price is 0, show blank.
                
                let displayQty = qty;
                let displayPPart = formatPrice(pPart);
                let displayTotalPart = formatPrice(totalPart);
                let displayPSvc = formatPrice(pSvc);
                let displayTotalSvc = formatPrice(totalSvc);

                if (!name) {
                    displayQty = "";
                    displayPPart = "";
                    displayTotalPart = "";
                    displayPSvc = "";
                    displayTotalSvc = "";
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border border-black p-1 text-center">${index++}</td>
                    <td class="border border-black p-1">${name}</td>
                    <td class="border border-black p-1 text-center">${displayQty}</td>
                    <td class="border border-black p-1 text-right">${displayPPart}</td>
                    <td class="border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50">${displayTotalPart}</td>
                    <td class="border border-black p-1 text-right">${displayPSvc}</td>
                    <td class="border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50">${displayTotalSvc}</td>
                    <td class="border border-black p-1">${tech}</td>
                `;
                tbody.appendChild(tr);
            });

            // Fill Empty Rows
            const needed = Math.max(0, 20 - rows.length);
            for(let i=0; i<needed; i++) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border border-black p-1 text-center text-white">.</td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1 bg-gray-50 print:bg-gray-50"></td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1 bg-gray-50 print:bg-gray-50"></td>
                    <td class="border border-black p-1"></td>
                `;
                tbody.appendChild(tr);
            }

            // Add Footer Row
            const footerRow = document.createElement('tr');
            footerRow.className = "font-bold bg-gray-100 print:bg-gray-100";
            
            // Do not display zeros in summary
            const displayPartTotal = totals.partTotal > 0 ? totals.partTotal.toFixed(2) : "";
            const displaySvcTotal = totals.svcTotal > 0 ? totals.svcTotal.toFixed(2) : "";

            footerRow.innerHTML = `
                <td class="border border-black p-2 text-right" colSpan="4">ჯამი:</td>
                <td class="border border-black p-2 text-right">${displayPartTotal}</td>
                <td class="border border-black p-2 text-right">ჯამი:</td>
                <td class="border border-black p-2 text-right">${displaySvcTotal}</td>
                <td class="border border-black p-2 bg-gray-300 print:bg-gray-300"></td>
            `;
            tbody.appendChild(footerRow);

            // Update Grand Total Text
            document.getElementById('out_grand_total').innerText = totals.grandTotal.toFixed(2);
        }

        function handlePrint() {
            switchTab('preview');
            setTimeout(() => {
                window.print();
            }, 100);
        }
    </script>
</body>
</html>
