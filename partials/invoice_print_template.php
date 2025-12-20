<?php
// Partial: invoice_print_template.php
// If $invoice is set, render server-side values; otherwise render placeholders for client-side JS to fill.
$server = isset($invoice) && is_array($invoice);
$serverItems = isset($items) && is_array($items);
function esc($s){ return htmlspecialchars((string)$s); }
?>
<div class="w-full overflow-x-auto pb-8 print:pb-0 print:overflow-visible flex justify-center bg-gray-200/50 p-4 rounded-lg print:bg-white print:p-0">
    <div class="bg-white p-8 shadow-xl print-no-shadow w-[210mm] min-w-[210mm] min-h-[297mm] a4-container print:w-full print:max-w-none print:min-w-0 print:p-0 mx-auto box-border text-black relative">
        
        <!-- Header -->
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
                <p class="font-bold text-lg">შპს "ოტო მოტორს ჰოლდესი"</p>
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
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center"><?php echo esc($invoice['creation_date']); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center" id="out_creation_date"></div>
                <?php endif; ?>

                <div class="font-bold whitespace-nowrap">კლიენტი:</div>
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-bold"><?php echo esc($invoice['customer_name'] ?: ($customer['full_name'] ?? '')); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-bold" id="out_customer_name"></div>
                <?php endif; ?>

                <div class="font-bold whitespace-nowrap">ავტომანქანა:</div>
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center"><?php echo esc($invoice['car_mark'] ?: ($customer['car_mark'] ?? '')); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center" id="out_car_mark"></div>
                <?php endif; ?>

                <div class="font-bold whitespace-nowrap">ა/მ სახ. #:</div>
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-mono uppercase"><?php echo esc($invoice['plate_number'] ?: ($customer['plate_number'] ?? '')); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-mono uppercase" id="out_plate_number"></div>
                <?php endif; ?>
            </div>

            <!-- Right -->
            <div class="grid grid-cols-[200px_1fr] gap-2 items-center">
                <div class="font-bold whitespace-nowrap">სერვისის დაწყების დრო:</div>
                <div class="border-b border-black px-2 h-6 flex items-center"></div>

                <div class="font-bold whitespace-nowrap">ტელ:</div>
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-mono"><?php echo esc($invoice['phone'] ?: ($customer['phone'] ?? '')); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-mono" id="out_phone_number"></div>
                <?php endif; ?>

                <div class="font-bold whitespace-nowrap">გარბენი:</div>
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center"><?php echo esc($invoice['mileage']); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center" id="out_mileage"></div>
                <?php endif; ?>

                <div class="font-bold whitespace-nowrap">სერვისის მენეჯერი:</div>
                <?php if ($server): ?>
                    <?php $smDisplay = $invoice['service_manager'] . (!empty($sm_username) ? ' ('.$sm_username.')' : ''); ?>
                    <div class="border-b border-black px-2 h-6 flex items-center"><?php echo esc($smDisplay); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center" id="out_service_manager"></div>
                <?php endif; ?>
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
                <tbody <?php echo $server ? '' : 'id="preview-table-body"'; ?>>
                    <?php if ($server && $serverItems):
                        $i = 1;
                        foreach ($items as $it) {
                            $qty = isset($it['qty']) ? (float)$it['qty'] : 0;
                            $pPart = isset($it['price_part']) ? (float)$it['price_part'] : 0;
                            $pSvc = isset($it['price_svc']) ? (float)$it['price_svc'] : 0;
                            $totalPart = $qty * $pPart;
                            $totalSvc = $qty * $pSvc;
                            echo "<tr>";
                            echo "<td class=\"border border-black p-1 text-center\">" . $i++ . "</td>";
                            echo "<td class=\"border border-black p-1\">" . esc($it['name'] ?? '') . "</td>";
                            echo "<td class=\"border border-black p-1 text-center\">" . ($qty ? $qty : '') . "</td>";
                            echo "<td class=\"border border-black p-1 text-right\">" . ($pPart ? number_format($pPart,2) : '') . "</td>";
                            echo "<td class=\"border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50\">" . ($totalPart ? number_format($totalPart,2) : '') . "</td>";
                            echo "<td class=\"border border-black p-1 text-right\">" . ($pSvc ? number_format($pSvc,2) : '') . "</td>";
                            echo "<td class=\"border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50\">" . ($totalSvc ? number_format($totalSvc,2) : '') . "</td>";
                            echo "<td class=\"border border-black p-1\">" . esc($it['tech'] ?? '') . "</td>";
                            echo "</tr>";
                        }
                    endif; ?>
                </tbody>
            </table>

            <!-- Grand Total -->
            <div class="flex justify-end mt-2">
                <div class="border border-black px-4 py-2 bg-yellow-100 print:bg-yellow-100 text-lg font-bold">
                    სულ გადასახდელი: <span id="out_grand_total"><?php echo $server ? number_format((float)($invoice['grand_total'] ?? 0), 2) : '0.00'; ?></span> ₾
                </div>
            </div>
        </div>

        <!-- Legal Text -->
        <div class="text-[9px] text-gray-600 space-y-2 mb-8 text-justify leading-tight">
            <p><strong>შენიშვნა:</strong> კლიენტის მიერ მოწოდებული ნაწილის ხარისხზე და გამართულობაზე კომპანია არ აგებს პასუხს. მანქანის შეკეთებისას თუ კლიენტი გადაწყვეტს ნაწილის მოწოდებას, ვალდებულია ნაწილი მოაწოდოს სერვისს არაუგვიანეს 2 სამუშაო დღისა, წინააღმდეგ შემთხვევაში машина გადაინაცვლებს კომპანიის ავტოსადგომზე, რა შემთხვევაშიც მანქანის დგომის დღიური საფასური იქნება 10 ლარი. თუ შენიშვნის ველში გარანტიის ვადა არ არის მითითებული გარანტია არ ვრცელდება. წინამდებარე დოკუმენტზე ხელმოწერით კლიენტი ადასტურებს რომ კომპანიის მიმართ პრეტენზია არ გააჩნია.</p>
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