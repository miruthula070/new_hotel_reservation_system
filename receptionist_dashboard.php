<?php
session_start();
require 'db.php';

if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'Receptionist') {
    header("Location: login.php");
    exit();
}

$message = "";

// 1. Guest Check-In Verification (Physical ID Match or OTP)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkin'])) {
    $bookingID = $_POST['bookingID'];
    $enteredOTP = $_POST['enteredOTP'];
    $idVerified = isset($_POST['idVerified']) ? 1 : 0;

    if(!$idVerified) {
        $message = "Error: Physical ID match verification is mandatory for check-in!";
    } else {
        try {
            $stmt = $pdo->prepare("CALL sp_CheckInGuest(?, ?, @msg)");
            $stmt->execute([$bookingID, $enteredOTP]);
            $out = $pdo->query("SELECT @msg AS statusMessage")->fetch();
            $stmt->closeCursor();
            $message = $out['statusMessage'];
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// 2. Handle Guest Cancels on Arrival / Post-Midnight No-Show
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_noshow_or_cancel'])) {
    $bookingID = $_POST['bookingID'];
    $actionType = $_POST['actionType']; // 'NoShow' or 'CancelOnArrival'

    try {
        $stmt = $pdo->prepare("CALL sp_HandleNoShowOrArrivalCancel(?, ?, @msg)");
        $stmt->execute([$bookingID, $actionType]);
        $out = $pdo->query("SELECT @msg AS statusMessage")->fetch();
        $stmt->closeCursor();
        $message = $out['statusMessage'];
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// 3. Handle During Stay Extension
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_stay'])) {
    $bookingID = $_POST['bookingID'];
    $newCheckOutDate = $_POST['newCheckOutDate'];

    try {
        $stmt = $pdo->prepare("CALL sp_ExtendGuestStay(?, ?, @msg)");
        $stmt->execute([$bookingID, $newCheckOutDate]);
        $out = $pdo->query("SELECT @msg AS statusMessage")->fetch();
        $stmt->closeCursor();
        $message = $out['statusMessage'];
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// 4. Handle End of Stay & Final Bill Generation / Payment Collection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout_guest'])) {
    $bookingID = $_POST['bookingID'];
    $finalPaidAmount = $_POST['finalPaidAmount'];

    try {
        $stmt = $pdo->prepare("CALL sp_CheckoutAndGenerateBill(?, ?, @msg)");
        $stmt->execute([$bookingID, $finalPaidAmount]);
        $out = $pdo->query("SELECT @msg AS statusMessage")->fetch();
        $stmt->closeCursor();
        $message = $out['statusMessage'];
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

$today = date('Y-m-d');

// Fetch Today's Arrivals
$stmt = $pdo->prepare("CALL sp_GetTodayArrivals(?)");
$stmt->execute([$today]);
$arrivals = $stmt->fetchAll();
$stmt->closeCursor();

// Fetch Active Stays (For Extensions / Checkouts)
$stmt2 = $pdo->prepare("CALL sp_GetActiveStays()");
$stmt2->execute();
$active_stays = $stmt2->fetchAll();
$stmt2->closeCursor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Hotel Reservation</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 font-sans">

<!-- Navigation Bar -->
<nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <!-- Brand -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-bell-concierge"></i>
                </div>
                <div>
                    <span class="text-lg font-bold text-slate-900 block leading-tight">Front Desk Operations</span>
                    <span class="text-xs text-slate-500 font-medium">Receptionist Portal &bull; <?php echo date('D, M d, Y'); ?></span>
                </div>
            </div>

            <!-- User Menu & Logout -->
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-2 bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200">
                    <div class="w-6 h-6 rounded-full bg-teal-600 text-white text-xs flex items-center justify-center font-bold">
                        <i class="fa-regular fa-user"></i>
                    </div>
                    <span class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <span class="text-xs bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full font-medium">Receptionist</span>
                </div>
                <a href="login.php?logout=true" 
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg border border-red-200 transition">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content Container -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Overview Stats Chips -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-plane-arrival"></i>
            </div>
            <div>
                <span class="text-xs text-slate-500 uppercase font-semibold tracking-wider block">Today's Arrivals</span>
                <span class="text-xl font-bold text-slate-900"><?php echo count($arrivals); ?></span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-bed"></i>
            </div>
            <div>
                <span class="text-xs text-slate-500 uppercase font-semibold tracking-wider block">Active Stays</span>
                <span class="text-xl font-bold text-slate-900"><?php echo count($active_stays); ?></span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-regular fa-calendar"></i>
            </div>
            <div>
                <span class="text-xs text-slate-500 uppercase font-semibold tracking-wider block">System Date</span>
                <span class="text-sm font-bold text-slate-800"><?php echo $today; ?></span>
            </div>
        </div>
    </div>

    <!-- Alert / Message Box -->
    <?php if($message): ?>
        <div class="flex items-start gap-3 bg-teal-50 border border-teal-200 text-teal-900 px-5 py-4 rounded-xl shadow-sm">
            <i class="fa-solid fa-circle-info text-teal-600 mt-0.5 text-lg flex-shrink-0"></i>
            <div class="text-sm leading-relaxed"><?php echo htmlspecialchars($message); ?></div>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: Arrival Day Management -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center flex-wrap gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-user-check text-teal-600"></i>
                    Arrival Day Management
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Check-In Verification, No-Show Processing & Arrival Cancellations</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-200 text-slate-700 rounded-full">
                Scheduled: <?php echo count($arrivals); ?>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/75 border-b border-slate-200 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Room</th>
                        <th class="py-3 px-4">Dates</th>
                        <th class="py-3 px-4">Verification & Check-In Action</th>
                        <th class="py-3 px-4 text-right">Post-Midnight / Cancel Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($arrivals)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <i class="fa-regular fa-calendar-xmark text-2xl block mb-2"></i>
                                No scheduled arrivals for today (<?php echo $today; ?>).
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($arrivals as $row): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-teal-700">
                                #<?php echo $row['bookingID']; ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                <?php echo htmlspecialchars($row['customerName']); ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-medium text-slate-800">#<?php echo $row['roomID']; ?></span>
                                <span class="text-xs text-slate-500 block"><?php echo htmlspecialchars($row['roomType']); ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-700 whitespace-nowrap">
                                <i class="fa-regular fa-calendar text-slate-400 mr-1"></i>
                                <?php echo $row['checkInDate'] . " to " . $row['checkOutDate']; ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <form method="POST" class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 space-y-2 max-w-xs">
                                    <input type="hidden" name="bookingID" value="<?php echo $row['bookingID']; ?>">
                                    <input type="text" name="enteredOTP" placeholder="Enter OTP / Booking ID" required 
                                           class="w-full border border-slate-300 rounded-lg px-2.5 py-1 text-xs text-slate-800 bg-white focus:ring-1 focus:ring-teal-500">
                                    
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-700 cursor-pointer">
                                        <input type="checkbox" name="idVerified" value="1" required class="rounded text-teal-600 focus:ring-0">
                                        <span>Physical ID Verified</span>
                                    </label>

                                    <button type="submit" name="checkin" 
                                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs py-1.5 rounded-lg transition shadow-sm flex items-center justify-center gap-1">
                                        <i class="fa-solid fa-check"></i>
                                        <span>Perform Check-In</span>
                                    </button>
                                </form>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex flex-col gap-1.5 items-end">
                                    <form method="POST">
                                        <input type="hidden" name="bookingID" value="<?php echo $row['bookingID']; ?>">
                                        <input type="hidden" name="actionType" value="CancelOnArrival">
                                        <button type="submit" name="mark_noshow_or_cancel" 
                                                class="bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition shadow-sm flex items-center gap-1">
                                            <i class="fa-solid fa-ban"></i> Cancel on Arrival
                                        </button>
                                    </form>

                                    <form method="POST" onsubmit="return confirm('Mark as No-Show (Post-midnight / Expired)?');">
                                        <input type="hidden" name="bookingID" value="<?php echo $row['bookingID']; ?>">
                                        <input type="hidden" name="actionType" value="NoShow">
                                        <button type="submit" name="mark_noshow_or_cancel" 
                                                class="bg-red-500 hover:bg-red-600 text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition shadow-sm flex items-center gap-1">
                                            <i class="fa-solid fa-user-xmark"></i> Mark No-Show
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SECTION 2: During Stay & End of Stay Management -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center flex-wrap gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-door-open text-blue-600"></i>
                    During Stay & End of Stay Management
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Stay Extensions, Final Bill Calculation & Guest Checkout</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-200 text-slate-700 rounded-full">
                In-House: <?php echo count($active_stays); ?>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/75 border-b border-slate-200 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Booking ID</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Room</th>
                        <th class="py-3 px-4">Current Check-Out</th>
                        <th class="py-3 px-4">Extend Stay</th>
                        <th class="py-3 px-4 text-right">End of Stay (Checkout & Bill)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($active_stays)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <i class="fa-regular fa-folder-open text-2xl block mb-2"></i>
                                No active checked-in guests currently.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($active_stays as $stay): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-600">
                                #<?php echo $stay['bookingID']; ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                <?php echo htmlspecialchars($stay['customerName']); ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-800">
                                Room #<?php echo $stay['roomID']; ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-700">
                                <i class="fa-regular fa-calendar-xmark text-slate-400 mr-1"></i>
                                <?php echo $stay['checkOutDate']; ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <form method="POST" class="inline-flex items-center gap-1 bg-slate-50 p-1.5 rounded-lg border border-slate-200">
                                    <input type="hidden" name="bookingID" value="<?php echo $stay['bookingID']; ?>">
                                    <input type="date" name="newCheckOutDate" required 
                                           class="border border-slate-300 rounded px-2 py-1 text-xs text-slate-700 bg-white focus:ring-1 focus:ring-blue-500">
                                    <button type="submit" name="extend_stay" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs px-2.5 py-1 rounded transition shadow-sm">
                                        Extend
                                    </button>
                                </form>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <form method="POST" onsubmit="return confirm('Generate final bill and checkout guest?');" 
                                      class="inline-flex items-center gap-1.5 bg-slate-50 p-1.5 rounded-lg border border-slate-200">
                                    <input type="hidden" name="bookingID" value="<?php echo $stay['bookingID']; ?>">
                                    <input type="number" step="0.01" name="finalPaidAmount" placeholder="Balance LKR" required 
                                           class="border border-slate-300 rounded px-2 py-1 text-xs text-slate-700 bg-white w-28 focus:ring-1 focus:ring-slate-800">
                                    <button type="submit" name="checkout_guest" 
                                            class="bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs px-3 py-1 rounded transition shadow-sm flex items-center gap-1">
                                        <i class="fa-solid fa-receipt"></i> Checkout & Bill
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>