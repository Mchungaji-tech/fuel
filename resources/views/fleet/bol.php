<?php
    $content = function () use ($title, $dispatch) {
?>
<div style="max-width:920px;margin:0 auto;padding:10px 0 40px;">
    <!-- Top toolbar with back and print buttons -->
    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <a href="<?= url('fleet') ?>" class="btn btn-ghost">← Back to Fleet Dispatches</a>
        <div style="display:flex;gap:10px;">
            <button onclick="window.print()" class="btn btn-brand">🖨️ Print / Save BOL as PDF</button>
        </div>
    </div>

    <!-- Official Bill of Lading Document Canvas -->
    <div id="bolDocument" style="background:#fff;color:#0F172A;border:2px solid #0F172A;border-radius:12px;padding:36px 40px;box-shadow:var(--shadow-lg);font-family:'Inter',system-ui,sans-serif;">
        <!-- Company Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0F172A;padding-bottom:18px;">
            <div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;background:#4F46E5;color:#fff;border-radius:8px;font-weight:800;font-size:18px;display:grid;place-items:center;">S</div>
                    <div style="font-size:22px;font-weight:900;letter-spacing:-.02em;color:#0F172A;">SARURA FUEL LOGISTICS LTD</div>
                </div>
                <div style="font-size:13px;color:#475569;margin-top:4px;">
                    Bulk Petroleum Road Transport & Energy Logistics<br>
                    Licence: EPRA/PTR/2024/0981 • KPC Loading Authorisation Code: SF-NBI-44<br>
                    Nairobi Office: Industrial Area, Enterprise Road • Tel: +254 700 000 000
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:12px;font-weight:800;letter-spacing:.08em;color:#4F46E5;text-transform:uppercase;">ORIGINAL CONSIGNMENT</div>
                <div style="font-size:24px;font-weight:900;color:#0F172A;margin-top:4px;"><?= htmlspecialchars($dispatch['bol_number']) ?></div>
                <div style="font-size:13px;color:#475569;font-weight:600;">Trip Ref: <?= htmlspecialchars($dispatch['trip_number']) ?></div>
            </div>
        </div>

        <div style="text-align:center;margin:18px 0;border-bottom:1px solid #E2E8F0;padding-bottom:12px;">
            <h2 style="margin:0;font-size:18px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;">
                PETROLEUM BILL OF LADING & BULK WAYBILL
            </h2>
            <div style="font-size:12.5px;color:#64748B;">Transported subject to standard petroleum haulage conditions & EPRA Kenya safety regulations</div>
        </div>

        <!-- Meta Information Matrix -->
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px;font-size:13.5px;">
            <div style="border:1px solid #CBD5E1;border-radius:8px;padding:14px;background:#F8FAFC;">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748B;letter-spacing:.06em;margin-bottom:6px;">SHIPPER / LOADING DEPOT</div>
                <div style="font-size:16px;font-weight:800;color:#0F172A;"><?= htmlspecialchars($dispatch['from_location']) ?></div>
                <div style="color:#475569;margin-top:2px;">Terminal Loading Bay • Gantry Metered</div>
                <div style="margin-top:8px;font-size:12.5px;"><b>Dispatch Date:</b> <?= htmlspecialchars($dispatch['dispatch_date']) ?></div>
            </div>

            <div style="border:1px solid #CBD5E1;border-radius:8px;padding:14px;background:#F8FAFC;">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748B;letter-spacing:.06em;margin-bottom:6px;">CONSIGNEE / DELIVERY DESTINATION</div>
                <div style="font-size:16px;font-weight:800;color:#0F172A;"><?= htmlspecialchars($dispatch['destination']) ?></div>
                <div style="color:#475569;margin-top:2px;">Offloading Storage Depot / Fuel Station</div>
                <div style="margin-top:8px;font-size:12.5px;"><b>Status:</b> <?= htmlspecialchars($dispatch['status']) ?></div>
            </div>
        </div>

        <!-- Carrier & Driver Specs -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;border:1px solid #CBD5E1;border-radius:8px;padding:14px;font-size:13px;background:#FFFFFF;">
            <div>
                <span style="font-size:11px;color:#64748B;text-transform:uppercase;font-weight:700;display:block;">Prime Mover Truck</span>
                <b style="font-size:15px;color:#0F172A;"><?= htmlspecialchars($dispatch['truck']) ?></b>
            </div>
            <div>
                <span style="font-size:11px;color:#64748B;text-transform:uppercase;font-weight:700;display:block;">Tanker Capacity</span>
                <b style="font-size:15px;color:#0F172A;"><?= number_format((int)$dispatch['truck_capacity']) ?> Litres</b>
            </div>
            <div>
                <span style="font-size:11px;color:#64748B;text-transform:uppercase;font-weight:700;display:block;">Assigned Driver</span>
                <b style="font-size:15px;color:#0F172A;"><?= htmlspecialchars($dispatch['driver']) ?></b>
            </div>
            <div>
                <span style="font-size:11px;color:#64748B;text-transform:uppercase;font-weight:700;display:block;">Product Initials</span>
                <b style="font-size:15px;color:#4F46E5;background:#EEF2FF;padding:2px 8px;border-radius:4px;display:inline-block;"><?= htmlspecialchars($dispatch['product']) ?></b>
            </div>
        </div>

        <!-- Cargo Manifest Table -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13.5px;">
            <thead>
                <tr style="background:#0F172A;color:#fff;">
                    <th style="padding:10px 14px;text-align:left;border:1px solid #0F172A;">Item</th>
                    <th style="padding:10px 14px;text-align:left;border:1px solid #0F172A;">Product Description</th>
                    <th style="padding:10px 14px;text-align:center;border:1px solid #0F172A;">Gross Volume (L)</th>
                    <th style="padding:10px 14px;text-align:center;border:1px solid #0F172A;">Net Loaded Volume (L)</th>
                    <th style="padding:10px 14px;text-align:right;border:1px solid #0F172A;">Transport Billing</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;font-weight:700;">1</td>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;">
                        <b><?= htmlspecialchars($dispatch['product']) ?></b> — Bulk Petroleum Haulage
                        <div style="font-size:12px;color:#64748B;">EPRA Standard Specification @ 20°C ambient</div>
                    </td>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;text-align:center;font-weight:600;">
                        <?= number_format((int)$dispatch['truck_capacity']) ?> L
                    </td>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;text-align:center;font-weight:800;color:#059669;">
                        <?= number_format((int)$dispatch['loaded_litres']) ?> L
                    </td>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;text-align:right;font-weight:800;">
                        <?= format_money($dispatch['transport_amount']) ?>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background:#F8FAFC;font-weight:800;">
                    <td colspan="3" style="padding:12px 14px;border:1px solid #CBD5E1;text-align:right;">TOTAL CARGO LOADED:</td>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;text-align:center;color:#059669;font-size:15px;">
                        <?= number_format((int)$dispatch['loaded_litres']) ?> Litres
                    </td>
                    <td style="padding:12px 14px;border:1px solid #CBD5E1;text-align:right;font-size:15px;">
                        <?= format_money($dispatch['transport_amount']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Security Seals -->
        <div style="border:1px dashed #94A3B8;border-radius:8px;padding:12px 16px;margin-bottom:28px;background:#F8FAFC;display:flex;justify-content:space-between;align-items:center;font-size:13px;">
            <div>
                <b style="color:#0F172A;text-transform:uppercase;">Security Seal Verifications:</b>
                <div style="color:#475569;margin-top:2px;">Tamper-proof serial numbers affixed at loading gantry</div>
            </div>
            <div style="font-weight:800;color:#DC2626;font-family:monospace;font-size:14px;">
                <?= htmlspecialchars($dispatch['seal_numbers'] ?: 'SL-89211, SL-89212, SL-89213, SL-89214') ?>
            </div>
        </div>

        <!-- Three-Part Verification Signatures -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:30px;font-size:12.5px;">
            <div style="border-top:1.5px solid #0F172A;padding-top:8px;">
                <b>1. Loaded & Released by (Shipper)</b>
                <div style="height:44px;"></div>
                <div>Name: ______________________</div>
                <div>Signature: __________________</div>
                <div>Date & Time: _______________</div>
                <div style="font-size:11px;color:#64748B;margin-top:4px;">Depot Gantry Supervisor Stamp</div>
            </div>

            <div style="border-top:1.5px solid #0F172A;padding-top:8px;">
                <b>2. Accepted in Good Order (Driver)</b>
                <div style="height:44px;"></div>
                <div>Driver: <?= htmlspecialchars($dispatch['driver']) ?></div>
                <div>Signature: __________________</div>
                <div>Date: <?= htmlspecialchars($dispatch['dispatch_date']) ?></div>
                <div style="font-size:11px;color:#64748B;margin-top:4px;">Sarura Carrier Authorization</div>
            </div>

            <div style="border-top:1.5px solid #0F172A;padding-top:8px;">
                <b>3. Received at Destination (Consignee)</b>
                <div style="height:44px;"></div>
                <div>Receiver: ____________________</div>
                <div>Signature: __________________</div>
                <div>Offload Litres: _____________ L</div>
                <div style="font-size:11px;color:#64748B;margin-top:4px;">Consignee Station Official Stamp</div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: #fff !important; color: #000 !important; }
    .no-print, .sidebar, .topbar, .mobile-bottom-nav { display: none !important; }
    .content { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    #bolDocument { border: none !important; box-shadow: none !important; padding: 10px !important; }
}
</style>
<?php };
require __DIR__ . '/../layouts/app.php';
?>
