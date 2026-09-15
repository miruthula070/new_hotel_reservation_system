<?php
session_start();
require 'db.php';

if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'Customer') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
$message = "";

// Function to validate date format (YYYY-MM-DD)
function isValidDate($dateString) {
    $d = DateTime::createFromFormat('Y-m-d', $dateString);
    return $d && $d->format('Y-m-d') === $dateString;
}

// 1. Handle Room Booking & QR Code Generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_room'])) {
    $roomID = $_POST['roomID'] ?? '';
    $checkIn = $_POST['checkInDate'] ?? '';
    $checkOut = $_POST['checkOutDate'] ?? '';
    $paidAmount = $_POST['paidAmount'] ?? 0.00;
    $agreePolicy = isset($_POST['agreePolicy']) ? 1 : 0;
    
    if (empty($checkIn) || empty($checkOut) || !isValidDate($checkIn) || !isValidDate($checkOut)) {
        $message = "Error: Please enter valid check-in and check-out dates.";
    } elseif (strtotime($checkIn) >= strtotime($checkOut)) {
        $message = "Error: Check-out date must be after check-in date.";
    } else {
        $otp = rand(100000, 999999);
        $dueTime = date('Y-m-d H:i:s', strtotime('+2 hours'));

        try {
            $stmt = $pdo->prepare("CALL sp_CreateBooking(?, ?, ?, ?, ?, ?, ?, ?, @b_id, @msg)");
            $stmt->execute([$userID, $roomID, $checkIn, $checkOut, $otp, $dueTime, $agreePolicy, $paidAmount]);
            
            $out = $pdo->query("SELECT @b_id AS bookingID, @msg AS statusMessage")->fetch();
            $stmt->closeCursor();
            
            if ($out['bookingID'] > 0) {
                $message = "Booking Successful! Booking ID: <b>#" . $out['bookingID'] . "</b> | QR Code / Check-in OTP: <b class='text-blue-700 text-base tracking-wider'>$otp</b> (Due Time: $dueTime)";
            } else {
                $message = "Error: " . $out['statusMessage'];
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// 2. Handle Booking Modification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modify_booking'])) {
    $bookingID = $_POST['bookingID'] ?? '';
    $newCheckIn = $_POST['newCheckIn'] ?? '';
    $newCheckOut = $_POST['newCheckOut'] ?? '';

    if (empty($newCheckIn) || empty($newCheckOut) || !isValidDate($newCheckIn) || !isValidDate($newCheckOut)) {
        $message = "Error: Please provide valid modification dates.";
    } elseif (strtotime($newCheckIn) >= strtotime($newCheckOut)) {
        $message = "Error: New check-out date must be after new check-in date.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE bookings SET checkInDate = ?, checkOutDate = ? WHERE bookingID = ? AND userID = ?");
            $stmt->execute([$newCheckIn, $newCheckOut, $bookingID, $userID]);
            $message = "Booking dates modified successfully!";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// 3. Handle Online Cancellation & Auto-Refund
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $bookingID = $_POST['bookingID'];

    try {
        $stmt = $pdo->prepare("CALL sp_CancelBooking(?, ?, @msg)");
        $stmt->execute([$bookingID, $userID]);
        $out = $pdo->query("SELECT @msg AS statusMessage")->fetch();
        $stmt->closeCursor();
        $message = $out['statusMessage'] ?? "Booking cancelled successfully and refund initiated!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch Available Rooms via Stored Procedure
$max_price = $_GET['max_price'] ?? null;
$stmt = $pdo->prepare("CALL sp_GetAvailableRooms(?)");
$stmt->execute([$max_price]);
$rooms = $stmt->fetchAll();
$stmt->closeCursor();

// Fetch Customer's Bookings with Direct SQL Join to guarantee Room ID and Room Type are included
$stmt2 = $pdo->prepare("SELECT b.*, r.roomType FROM bookings b LEFT JOIN rooms r ON b.roomID = r.roomID WHERE b.userID = ? ORDER BY b.bookingID DESC");
$stmt2->execute([$userID]);
$my_bookings = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Hotel Reservation</title>
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
                <div class="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-hotel"></i>
                </div>
                <div>
                    <span class="text-lg font-bold text-slate-900 block leading-tight">Hotel Reservation</span>
                    <span class="text-xs text-slate-500 font-medium">Customer Portal</span>
                </div>
            </div>

            <!-- User Menu & Logout -->
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-2 bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200">
                    <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">
                        <i class="fa-regular fa-user"></i>
                    </div>
                    <span class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Customer</span>
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

    <!-- Alert / Message Box -->
    <?php if($message): ?>
        <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 text-blue-900 px-5 py-4 rounded-xl shadow-sm">
            <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 text-lg flex-shrink-0"></i>
            <div class="text-sm leading-relaxed"><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: Manage My Bookings -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center flex-wrap gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-blue-600"></i>
                    Manage My Bookings & Payment Details
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Pre-Arrival Portal & Online Check-In Verification</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-200 text-slate-700 rounded-full">
                Total: <?php echo count($my_bookings); ?>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/75 border-b border-slate-200 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Booking ID</th>
                        <th class="py-3 px-4">Room</th>
                        <th class="py-3 px-4">Dates</th>
                        <th class="py-3 px-4">Status & OTP</th>
                        <th class="py-3 px-4">Payment & Due Window</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($my_bookings)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <i class="fa-regular fa-folder-open text-2xl block mb-2"></i>
                                No active bookings found. Browse available rooms below to book a stay!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($my_bookings as $mb): 
                            $isConfirmed = (($mb['bookingStatus'] ?? '') == 'Confirmed');
                            $isPaid = (($mb['paymentStatus'] ?? '') == 'Paid');
                        ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-600">
                                #<?php echo htmlspecialchars($mb['bookingID']); ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-900">Room #<?php echo htmlspecialchars($mb['roomID']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($mb['roomType'] ?? ''); ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-medium text-slate-700 whitespace-nowrap">
                                <i class="fa-regular fa-calendar text-slate-400 mr-1"></i>
                                <?php echo htmlspecialchars($mb['checkInDate'] . " to " . $mb['checkOutDate']); ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex flex-col gap-1 items-start">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $isConfirmed ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo htmlspecialchars($mb['bookingStatus'] ?? 'Confirmed'); ?>
                                    </span>
                                    <span class="text-xs bg-slate-100 border border-slate-200 px-2 py-0.5 rounded font-mono font-bold text-slate-800">
                                        OTP: <?php echo htmlspecialchars($mb['otp'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs space-y-0.5">
                                <div>
                                    Status: 
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold <?php echo $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                        <?php echo htmlspecialchars($mb['paymentStatus'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                                <div class="text-slate-500">
                                    Due: <b><?php echo htmlspecialchars($mb['dueTime'] ?? 'N/A'); ?></b>
                                </div>
                                <?php if(!empty($mb['refundStatus']) && $mb['refundStatus'] != 'N/A'): ?>
                                    <div class="text-red-600 font-medium">
                                        Refund: <?php echo htmlspecialchars($mb['refundStatus']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <?php if($isConfirmed): ?>
                                    <div class="flex flex-col sm:flex-row items-end sm:items-center justify-end gap-1.5">
                                        <!-- Modify Form -->
                                        <form method="POST" class="inline-flex items-center gap-1 bg-slate-50 p-1.5 rounded-lg border border-slate-200">
                                            <input type="hidden" name="bookingID" value="<?php echo $mb['bookingID']; ?>">
                                            <input type="date" name="newCheckIn" value="<?php echo htmlspecialchars($mb['checkInDate']); ?>" required 
                                                   class="border border-slate-300 rounded px-1.5 py-1 text-xs text-slate-700 bg-white focus:ring-1 focus:ring-amber-500">
                                            <input type="date" name="newCheckOut" value="<?php echo htmlspecialchars($mb['checkOutDate']); ?>" required 
                                                   class="border border-slate-300 rounded px-1.5 py-1 text-xs text-slate-700 bg-white focus:ring-1 focus:ring-amber-500">
                                            <button type="submit" name="modify_booking" 
                                                    class="bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs px-2.5 py-1 rounded transition shadow-sm">
                                                Modify
                                            </button>
                                        </form>

                                        <!-- Cancel Form with Auto-Refund -->
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel? Auto-refund will be initiated.');" class="inline">
                                            <input type="hidden" name="bookingID" value="<?php echo $mb['bookingID']; ?>">
                                            <button type="submit" name="cancel_booking" 
                                                    class="bg-red-500 hover:bg-red-600 text-white font-semibold text-xs px-2.5 py-1.5 rounded-lg transition shadow-sm">
                                                Cancel & Refund
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">Cancelled / Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SECTION 2: Search & Book Available Rooms -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center flex-wrap gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-bed text-blue-600"></i>
                    Search & Book Available Rooms
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Filter by maximum price and reserve your room directly</p>
            </div>

            <!-- Price Filter Form -->
            <form method="GET" action="customer_dashboard.php" class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-600" for="max_price">Max Price:</label>
                <input type="number" id="max_price" name="max_price" value="<?php echo htmlspecialchars($max_price ?? ''); ?>" placeholder="LKR Max Price"
                       class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs text-slate-800 bg-white w-32 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" 
                        class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition shadow-sm flex items-center gap-1">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <?php if(!empty($max_price)): ?>
                    <a href="customer_dashboard.php" class="text-xs text-slate-500 hover:text-slate-700 underline ml-1">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/75 border-b border-slate-200 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Room ID</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Price (LKR)</th>
                        <th class="py-3 px-4 text-right">Reservation Form</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($rooms)): ?>
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">
                                <i class="fa-solid fa-circle-exclamation text-2xl block mb-2"></i>
                                No rooms available matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($rooms as $room): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-900">
                                #<?php echo $room['roomID']; ?>
                            </td>
                            <td class="py-3.5 px-4 font-medium text-slate-800">
                                <?php echo htmlspecialchars($room['roomType']); ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-slate-900 text-sm">
                                    LKR <?php echo number_format($room['price'], 2); ?>
                                </span>
                                <span class="text-xs text-slate-400 block">per night</span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <form method="POST" class="inline-flex flex-wrap items-center justify-end gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <input type="hidden" name="roomID" value="<?php echo $room['roomID']; ?>">
                                    
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-slate-500 font-medium">In:</span>
                                        <input type="date" name="checkInDate" required 
                                               class="border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-700 bg-white focus:ring-1 focus:ring-blue-500">
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-slate-500 font-medium">Out:</span>
                                        <input type="date" name="checkOutDate" required 
                                               class="border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-700 bg-white focus:ring-1 focus:ring-blue-500">
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-slate-500 font-medium">Advance:</span>
                                        <input type="number" name="paidAmount" step="0.01" placeholder="Amount" required 
                                               class="border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-700 bg-white w-24 focus:ring-1 focus:ring-blue-500">
                                    </div>

                                    <label class="flex items-center gap-1 text-xs text-slate-600 cursor-pointer ml-1">
                                        <input type="checkbox" name="agreePolicy" required class="rounded text-blue-600 focus:ring-0">
                                        <span>Agree to policy</span>
                                    </label>

                                    <button type="submit" name="book_room" 
                                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition shadow-sm flex items-center gap-1 ml-1">
                                        <i class="fa-solid fa-check"></i>
                                        <span>Confirm Booking</span>
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