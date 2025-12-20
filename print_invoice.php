<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    die('Invoice ID required');
}

$stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) die('Invoice not found');

$items = json_decode($invoice['items'], true) ?: [];

// Resolve customer
$customer = null;
if (!empty($invoice['customer_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$invoice['customer_id']]);
    $customer = $stmt->fetch();
}

// Resolve service manager username if id present
$sm_username = '';
if (!empty($invoice['service_manager_id'])) {
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$invoice['service_manager_id']]);
    $sm = $stmt->fetch();
    if ($sm) $sm_username = $sm['username'];
}

// Totals
$partsTotal = number_format((float)$invoice['parts_total'], 2);
$svcTotal = number_format((float)$invoice['service_total'], 2);
$grandTotal = number_format((float)$invoice['grand_total'], 2);

?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Invoice #<?php echo $invoice['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { margin: 0; size: A4; }
            html, body { height: 100%; margin: 0 !important; padding: 0 !important; overflow: hidden; }
            .print-hidden { display: none !important; }
            .a4-container { height: 297mm !important; max-height: 297mm !important; overflow: hidden !important; }
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #000; }
    </style>
</head>
<body class="bg-white text-black">
    <div class="mx-auto p-4 w-[210mm] min-w-[210mm] min-h-[297mm] a4-container box-border">
        <div class="grid grid-cols-2 mb-6 gap-8 items-start">
            <div class="text-sm space-y-1">
                <div class="mb-2 text-slate-800">
                    <img src="https://service.otoexpress.ge/wp-content/uploads/2023/08/cropped-otomotors.png" width="50%" alt="Logo">
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

        <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 text-sm">
            <div class="grid grid-cols-[140px_1fr] gap-2 items-center">
                <div class="font-bold whitespace-nowrap">შემოსვლის დრო:</div>
                <div class="border-b border-black px-2 h-6 flex items-center"><?php echo $invoice['creation_date']; ?></div>

                <div class="font-bold whitespace-nowrap">კლიენტი:</div>
                <div class="border-b border-black px-2 h-6 flex items-center font-bold"><?php echo htmlspecialchars($invoice['customer_name'] ?: ($customer['full_name'] ?? '')); ?></div>

                <div class="font-bold whitespace-nowrap">ავტომანქანა:</div>
                <div class="border-b border-black px-2 h-6 flex items-center"><?php echo htmlspecialchars($invoice['car_mark'] ?: ($customer['car_mark'] ?? '')); ?></div>

                <div class="font-bold whitespace-nowrap">ა/მ სახ. #:</div>
                <div class="border-b border-black px-2 h-6 flex items-center font-mono uppercase"><?php echo htmlspecialchars($invoice['plate_number'] ?: ($customer['plate_number'] ?? '')); ?></div>
            </div>

            <div class="grid grid-cols-[160px_1fr] gap-2 items-center">
                <div class="font-bold whitespace-nowrap">სერვისის დაწყების დრო:</div>
                <div class="border-b border-black px-2 h-6 flex items-center"></div>

                <div class="font-bold whitespace-nowrap">ტელ:</div>
                <div class="border-b border-black px-2 h-6 flex items-center font-mono"><?php echo htmlspecialchars($invoice['phone'] ?: ($customer['phone'] ?? '')); ?></div>

                <div class="font-bold whitespace-nowrap">გარბენი:</div>
                <div class="border-b border-black px-2 h-6 flex items-center"><?php echo htmlspecialchars($invoice['mileage']); ?></div>

                <div class="font-bold whitespace-nowrap">სერვისის მენეჯერი:</div>
                <div class="border-b border-black px-2 h-6 flex items-center"><?php echo htmlspecialchars($invoice['service_manager'] . (!empty($sm_username) ? ' ('.$sm_username.')' : '')); ?></div>
            </div>
        </div>

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
                <tbody>
                    <?php
                    $i = 1;
                    foreach ($items as $it) {
                        $qty = isset($it['qty']) ? (float)$it['qty'] : 0;
                        $pPart = isset($it['price_part']) ? (float)$it['price_part'] : 0;
                        $pSvc = isset($it['price_svc']) ? (float)$it['price_svc'] : 0;
                        $totalPart = $qty * $pPart;
                        $totalSvc = $qty * $pSvc;
                        echo "<tr>";
                        echo "<td class=\"border border-black p-1 text-center\">" . $i++ . "</td>";
                        echo "<td class=\"border border-black p-1\">" . htmlspecialchars($it['name'] ?? '') . "</td>";
                        echo "<td class=\"border border-black p-1 text-center\">" . ($qty ? $qty : '') . "</td>";
                        echo "<td class=\"border border-black p-1 text-right\">" . ($pPart ? number_format($pPart,2) : '') . "</td>";
                        echo "<td class=\"border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50\">" . ($totalPart ? number_format($totalPart,2) : '') . "</td>";
                        echo "<td class=\"border border-black p-1 text-right\">" . ($pSvc ? number_format($pSvc,2) : '') . "</td>";
                        echo "<td class=\"border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50\">" . ($totalSvc ? number_format($totalSvc,2) : '') . "</td>";
                        echo "<td class=\"border border-black p-1\">" . htmlspecialchars($it['tech'] ?? '') . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <div class="flex justify-end mt-2">
                <div class="border border-black px-4 py-2 bg-yellow-100 print:bg-yellow-100 text-lg font-bold">
                    სულ გადასახდელი: <?php echo $grandTotal; ?> ₾
                </div>
            </div>
        </div>

        <div class="text-[9px] text-gray-600 space-y-2 mb-8 text-justify leading-tight">
            <p><strong>შენიშვნა:</strong> კლიენტის მიერ მოწოდებული ნაწილის ხარისხზე და გამართულობაზე კომპანია არ აგებს პასუხს. მანქანის შეკეთებისას თუ კლიენტი გადაწყვეტს ნაწილის მოწოდებას, ვალდებულია ნაწილი მოაწოდოს სერვისს არაუგვიანეს 2 სამუშაო დღისა, წინააღმდეგ შემთხვევაში მანქანა გადაინაცვლებს კომპანიის ავტოსადგომზე, რა შემთხვევაშიც მანქანის დგომის დღიური საფასური იქნება 10 ლარი. თუ შენიშვნის ველში გარანტიის ვადა არ არის მითითებული გარანტია არ ვრცელდება. წინამდებარე დოკუმენტზე ხელმოწერით კლიენტი ადასტურებს რომ კომპანიის მიმართ პრეტენზია არ გააჩნია.</p>
            <p><strong>საგარანტიო პირობები:</strong> 1. აალების სანთლების საგარანტიო ვადა განისაზღვრება კილომეტრაჟით, რომელიც შეადგენს 1000 კმ-ს. 2. სამუხრუჭე ხუნდების საგარანტიო ვადა განისაზღვრება მონტაჟიდან 7 დღის ვადით.</p>
            <p class="italic mt-4">Oneclub: საიდან გაიგეთ ჩვენს შესახებ? ________________________</p>
        </div>

        <div class="grid grid-cols-2 gap-20 mt-8 text-sm absolute bottom-12 w-full left-0 px-8 box-border">
            <div class="border-t border-black pt-2 text-center">მენეჯერის ხელმოწერა</div>
            <div class="border-t border-black pt-2 text-center">კლიენტის ხელმოწერა</div>
        </div>
    </div>

    <script>
        // Auto print when loaded
        window.addEventListener('load', function() { setTimeout(() => { window.print(); }, 200); });
    </script>
</body>
</html>