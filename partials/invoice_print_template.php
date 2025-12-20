<?php
// Partial: invoice_print_template.php
// If $invoice is set, render server-side values; otherwise render placeholders for client-side JS to fill.
$server = isset($invoice) && is_array($invoice);
$serverItems = isset($items) && is_array($items);
function esc($s){ return htmlspecialchars((string)$s); }
?>
<div class="w-full overflow-x-auto pb-8 print:pb-0 print:overflow-visible flex justify-center bg-gray-200/50 p-4 rounded-lg print:bg-white print:p-0">
    <div class="bg-white p-8 shadow-xl print-no-shadow w-[210mm] min-w-[210mm] min-h-[297mm] a4-container print:w-full print:max-w-none print:min-w-0 print:p-4 mx-auto box-border text-black relative">

        <!-- Header -->
        <div class="grid grid-cols-2 mb-4 gap-8 items-start">
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
        <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-4 text-sm">
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

                <div class="font-bold whitespace-nowrap">VIN:</div>
                <?php if ($server): ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-mono"><?php echo esc($invoice['vin'] ?: ($customer['vin'] ?? '')); ?></div>
                <?php else: ?>
                    <div class="border-b border-black px-2 h-6 flex items-center font-mono" id="out_vin"></div>
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

        <!-- Customer Notes -->
        <?php if ($server && $customer && !empty($customer['notes'])): ?>
        <div class="mb-4 text-sm">
            <div class="font-bold whitespace-nowrap">შენიშვნები:</div>
            <div class="border border-black px-2 py-1 mt-1 bg-gray-50 print:bg-gray-50"><?php echo nl2br(esc($customer['notes'])); ?></div>
        </div>
        <?php endif; ?>



        <!-- Table -->
        <div class="mb-2">
<?php if ($server && $serverItems):
    $computedParts = 0.0;
    $computedSvc = 0.0;
    foreach ($items as $it) {
        $qty = isset($it['qty']) ? (float)$it['qty'] : 0;
        $pPart = isset($it['price_part']) ? (float)$it['price_part'] : 0;
        $pSvc = isset($it['price_svc']) ? (float)$it['price_svc'] : 0;
        $computedParts += $qty * $pPart;
        $computedSvc += $qty * $pSvc;
    }
    $computedGrand = $computedParts + $computedSvc;
endif; ?>
            <table class="w-full text-[8px] border-collapse border border-black">
                <thead>
                    <tr class="bg-gray-200 print:bg-gray-200">
                        <th class="border border-black p-0.5 w-8 text-center">#</th>
                        <th class="border border-black p-0.5 text-left">ნაწილის და სერვისის დასახელება</th>
                        <th class="border border-black p-0.5 w-12 text-center">რაოდ.</th>
                        <th class="border border-black p-0.5 w-20 text-right">ფასი ნაწილი</th>
                        <th class="border border-black p-0.5 w-20 text-right">თანხა</th>
                        <th class="border border-black p-0.5 w-20 text-right">ფასი სერვისი</th>
                        <th class="border border-black p-0.5 w-20 text-right">თანხა</th>
                        <th class="border border-black p-0.5 w-24 text-left">შემსრულებელი</th>
                    </tr>
                </thead>
                <tbody <?php echo $server ? '' : 'id="preview-table-body"'; ?>>
                    <?php if ($server && $serverItems):
                        $i = 1;
                        foreach ($items as $it) {
                            $name = trim($it['name'] ?? '');
                            $qty = isset($it['qty']) ? (float)$it['qty'] : 0;
                            $pPart = isset($it['price_part']) ? (float)$it['price_part'] : 0;
                            $pSvc = isset($it['price_svc']) ? (float)$it['price_svc'] : 0;
                            $tech = $it['tech'] ?? ''; 

                            $displayQty = $qty;
                            $displayPPart = $pPart > 0 ? number_format($pPart,2) : '';
                            $displayTotalPart = ($qty * $pPart) > 0 ? number_format($qty * $pPart, 2) : '';
                            $displayPSvc = $pSvc > 0 ? number_format($pSvc,2) : '';
                            $displayTotalSvc = ($qty * $pSvc) > 0 ? number_format($qty * $pSvc, 2) : '';

                            if ($name === '') {
                                $displayQty = '';
                                $displayPPart = '';
                                $displayTotalPart = '';
                                $displayPSvc = '';
                                $displayTotalSvc = '';
                            }

                            echo "<tr>";
                            echo "<td class=\"border border-black p-0.5 text-center\">" . $i++ . "</td>";
                            echo "<td class=\"border border-black p-0.5\">" . esc($name) . "</td>";
                            echo "<td class=\"border border-black p-0.5 text-center\">" . $displayQty . "</td>";
                            echo "<td class=\"border border-black p-0.5 text-right\">" . $displayPPart . "</td>";
                            echo "<td class=\"border border-black p-0.5 text-right font-semibold bg-gray-50 print:bg-gray-50\">" . $displayTotalPart . "</td>";
                            echo "<td class=\"border border-black p-0.5 text-right\">" . $displayPSvc . "</td>";
                            echo "<td class=\"border border-black p-0.5 text-right font-semibold bg-gray-50 print:bg-gray-50\">" . $displayTotalSvc . "</td>";
                            echo "<td class=\"border border-black p-0.5\">" . esc($tech) . "</td>";
                            echo "</tr>";
                        }

                        // Fill empty rows up to 15 to fit one page
                        $rowsCount = count($items);
                        $needed = max(0, 15 - $rowsCount);
                        for ($j = 0; $j < $needed; $j++) {
                            echo "<tr>";
                            echo "<td class=\"border border-black p-0.5 text-center text-white\">.</td>";
                            echo "<td class=\"border border-black p-0.5\"></td>";
                            echo "<td class=\"border border-black p-0.5\"></td>";
                            echo "<td class=\"border border-black p-0.5\"></td>";
                            echo "<td class=\"border border-black p-0.5 bg-gray-50 print:bg-gray-50\"></td>";
                            echo "<td class=\"border border-black p-0.5\"></td>";
                            echo "<td class=\"border border-black p-0.5 bg-gray-50 print:bg-gray-50\"></td>";
                            echo "<td class=\"border border-black p-0.5\"></td>";
                            echo "</tr>";
                        }

                        // Add footer row (totals) matching preview logic (hide zeros)
                        $displayPartTotal = ($computedParts ?? 0) > 0 ? number_format($computedParts, 2) : '';
                        $displaySvcTotal = ($computedSvc ?? 0) > 0 ? number_format($computedSvc, 2) : '';
                        echo "<tr class=\"font-bold bg-gray-100 print:bg-gray-100\">";
                        echo "<td class=\"border border-black p-0.5 text-right\" colSpan=\"4\">ჯამი:</td>";
                        echo "<td class=\"border border-black p-0.5 text-right\">" . $displayPartTotal . "</td>";
                        echo "<td class=\"border border-black p-0.5 text-right\">ჯამი:</td>";
                        echo "<td class=\"border border-black p-0.5 text-right\">" . $displaySvcTotal . "</td>";
                        echo "<td class=\"border border-black p-0.5 bg-gray-300 print:bg-gray-300\"></td>";
                        echo "</tr>";
                    endif; ?>
                </tbody>
            </table>

            <!-- Grand Total -->
            <div class="flex justify-end mt-1">
                <div class="border border-black px-4 py-2 bg-yellow-100 print:bg-yellow-100 text-lg font-bold">
                    სულ გადასახდელი: <span id="out_grand_total"><?php $total = $server ? (float)($computedGrand ?? ($invoice['grand_total'] ?? 0)) : 0; echo $total > 0 ? number_format($total, 2) . ' ₾' : ''; ?></span>
                    <input type="text" class="border-b border-black bg-transparent text-lg font-bold w-24 ml-2 text-center" placeholder="____" />
                </div>
            </div>
        </div>

        <!-- Legal Text -->
        <div class="text-[8px] text-gray-600 space-y-1 mb-4 text-justify leading-tight">
            <p><strong>შენიშვნა:</strong> კლიენტის მიერ მოწოდებული ნაწილის ხარისხზე და გამართულობაზე კომპანია არ აგებს პასუხს. მანქანის შეკეთებისას თუ კლიენტი გადაწყვეტს ნაწილის მოწოდებას, ვალდებულია ნაწილი მოაწოდოს სერვისს არაუგვიანეს 2 სამუშაო დღისა, წინააღმდეგ შემთხვევაში машина გადაინაცვლებს კომპანიის ავტოსადგომზე, რა შემთხვევაშიც მანქანის დგომის დღიური საფასური იქნება 10 ლარი. თუ შენიშვნის ველში გარანტიის ვადა არ არის მითითებული გარანტია არ ვრცელდება. წინამდებარე დოკუმენტზე ხელმოწერით კლიენტი ადასტურებს რომ კომპანიის მიმართ პრეტენზია არ გააჩნია.</p>
            <p><strong>საგარანტიო პირობები:</strong> 1. აალების სანთლების საგარანტიო ვადა განისაზღვრება კილომეტრაჟით, რომელიც შეადგენს 1000 კმ-ს. 2. სამუხრუჭე ხუნდების საგარანტიო ვადა განისაზღვრება მონტაჟიდან 7 დღის ვადით.</p>
            <p class="italic mt-2">Oneclub: საიდან გაიგეთ ჩვენს შესახებ? ________________________</p>
        </div>

        <!-- Signatures -->
        <div class="grid grid-cols-2 gap-20 mt-4 text-sm">
            <div class="border-t border-black pt-2 text-center">მენეჯერის ხელმოწერა</div>
            <div class="border-t border-black pt-2 text-center">კლიენტის ხელმოწერა</div>
        </div>
    </div>
</div>