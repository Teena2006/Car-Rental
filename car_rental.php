<?php
session_start();

/* =========================
   CAR RENTAL SYSTEM
   SINGLE FILE PROJECT
   File: car_rental.php
   ========================= */

$host = "localhost";
$user = "root";
$pass = "";
$db   = "car_rental";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

$conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
$conn->select_db($db);
$conn->set_charset("utf8mb4");

/* ---------- TABLES ---------- */
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(500) DEFAULT '',
    status ENUM('Available','Unavailable') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    car_id INT NOT NULL,
    pickup_date DATE NOT NULL,
    return_date DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* ---------- DEFAULT ADMIN ---------- */
$check = $conn->query("SELECT id FROM admins LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO admins(username,password) VALUES('admin','admin123')");
}

/* ---------- DEFAULT CARS ---------- */
$check = $conn->query("SELECT id FROM cars LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO cars(name,model,price,image) VALUES
    ('Maruti Swift','2024',1500,''),
    ('Hyundai i20','2024',1800,''),
    ('Tata Nexon','2024',2200,''),
    ('Maruti Baleno','2024',1700,'')");
}

/* ---------- LOGOUT ---------- */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: car_rental.php");
    exit;
}

/* ---------- REGISTER ---------- */
$message = "";

if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($name == "" || $email == "" || $password == "") {
        $message = "Please fill all fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");
        $stmt->bind_param("sss", $name, $email, $password);
        if ($stmt->execute()) {
            $message = "Registration successful. Please login.";
        } else {
            $message = "Email already exists.";
        }
    }
}

/* ---------- USER LOGIN ---------- */
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND password=?");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $_SESSION['user'] = $result->fetch_assoc();
        header("Location: car_rental.php?page=cars");
        exit;
    } else {
        $message = "Invalid email or password.";
    }
}

/* ---------- ADMIN LOGIN ---------- */
if (isset($_POST['admin_login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $_SESSION['admin'] = $result->fetch_assoc();
        header("Location: car_rental.php?page=admin");
        exit;
    } else {
        $message = "Invalid admin login.";
    }
}

/* ---------- ADD CAR ---------- */
if (isset($_POST['add_car']) && isset($_SESSION['admin'])) {
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $price = (float)$_POST['price'];
    $image = trim($_POST['image']);

    $stmt = $conn->prepare("INSERT INTO cars(name,model,price,image) VALUES(?,?,?,?)");
    $stmt->bind_param("ssds", $name, $model, $price, $image);
    $stmt->execute();

    header("Location: car_rental.php?page=manage_cars");
    exit;
}

/* ---------- DELETE CAR ---------- */
if (isset($_GET['delete_car']) && isset($_SESSION['admin'])) {
    $id = (int)$_GET['delete_car'];
    $conn->query("DELETE FROM cars WHERE id=$id");
    header("Location: car_rental.php?page=manage_cars");
    exit;
}

/* ---------- BOOK CAR ---------- */
if (isset($_POST['book_car']) && isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    $car_id = (int)$_POST['car_id'];
    $pickup = $_POST['pickup_date'];
    $return = $_POST['return_date'];

    $d1 = new DateTime($pickup);
    $d2 = new DateTime($return);

    if ($d2 < $d1) {
        $message = "Return date must be after pickup date.";
    } else {
        $days = $d1->diff($d2)->days + 1;

        $carResult = $conn->query("SELECT * FROM cars WHERE id=$car_id");
        $car = $carResult->fetch_assoc();

        $overlap = $conn->query("SELECT id FROM bookings
            WHERE car_id=$car_id
            AND status='Approved'
            AND pickup_date <= '$return'
            AND return_date >= '$pickup'");

        if ($overlap->num_rows > 0) {
            $message = "Car is already booked for these dates.";
        } else {
            $total = $days * $car['price'];

            $stmt = $conn->prepare("INSERT INTO bookings
                (user_id,car_id,pickup_date,return_date,total_price)
                VALUES(?,?,?,?,?)");
            $stmt->bind_param("iissd", $user_id, $car_id, $pickup, $return, $total);
            $stmt->execute();

            $message = "Booking request submitted successfully.";
        }
    }
}

/* ---------- APPROVE / REJECT ---------- */
if (isset($_GET['approve']) && isset($_SESSION['admin'])) {
    $id = (int)$_GET['approve'];
    $conn->query("UPDATE bookings SET status='Approved' WHERE id=$id");
    header("Location: car_rental.php?page=bookings");
    exit;
}

if (isset($_GET['reject']) && isset($_SESSION['admin'])) {
    $id = (int)$_GET['reject'];
    $conn->query("UPDATE bookings SET status='Rejected' WHERE id=$id");
    header("Location: car_rental.php?page=bookings");
    exit;
}

$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Car Rental System</title>
<style>
*{box-sizing:border-box}
body{
    margin:0;
    font-family:Arial,sans-serif;
    background:#f4f6f8;
    color:#222;
}
nav{
    background:#111827;
    padding:15px 6%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
}
nav .logo{color:white;font-size:24px;font-weight:bold}
nav a{
    color:white;
    text-decoration:none;
    margin:5px 10px;
}
.container{width:90%;max-width:1100px;margin:30px auto}
.hero{
    background:#111827;
    color:white;
    padding:70px 30px;
    text-align:center;
    border-radius:15px;
}
.hero h1{font-size:42px;margin:10px}
.hero p{font-size:18px}
.btn{
    display:inline-block;
    padding:11px 18px;
    background:#2563eb;
    color:white;
    border:0;
    border-radius:7px;
    text-decoration:none;
    cursor:pointer;
    margin:4px;
}
.btn.green{background:#16a34a}
.btn.red{background:#dc2626}
.btn.gray{background:#6b7280}
.form-box{
    background:white;
    max-width:450px;
    margin:30px auto;
    padding:30px;
    border-radius:12px;
    box-shadow:0 3px 15px #ddd;
}
input,select{
    width:100%;
    padding:12px;
    margin:8px 0 15px;
    border:1px solid #ccc;
    border-radius:6px;
}
.card-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:20px;
}
.card{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 3px 12px #ddd;
}
.car-image{
    width:100%;
    height:150px;
    object-fit:cover;
    border-radius:8px;
    background:#e5e7eb;
}
.alert{
    max-width:700px;
    margin:15px auto;
    padding:14px;
    background:#fff3cd;
    border-radius:7px;
    text-align:center;
}
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}
th,td{
    padding:12px;
    border:1px solid #ddd;
    text-align:left;
}
th{background:#111827;color:white}
.stat-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:20px;
}
.stat{
    background:white;
    padding:25px;
    text-align:center;
    border-radius:10px;
    box-shadow:0 3px 12px #ddd;
}
footer{
    margin-top:50px;
    background:#111827;
    color:white;
    text-align:center;
    padding:20px;
}
</style>
</head>
<body>

<nav>
    <div class="logo">🚗 Car Rental</div>
    <div>
        <a href="car_rental.php">Home</a>
        <a href="car_rental.php?page=cars">Cars</a>

        <?php if(isset($_SESSION['user'])): ?>
            <a href="car_rental.php?page=my_bookings">My Bookings</a>
            <a href="car_rental.php?logout=1">Logout</a>
        <?php elseif(isset($_SESSION['admin'])): ?>
            <a href="car_rental.php?page=admin">Dashboard</a>
            <a href="car_rental.php?page=manage_cars">Cars</a>
            <a href="car_rental.php?page=bookings">Bookings</a>
            <a href="car_rental.php?logout=1">Logout</a>
        <?php else: ?>
            <a href="car_rental.php?page=login">Login</a>
            <a href="car_rental.php?page=register">Register</a>
            <a href="car_rental.php?page=admin_login">Admin</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">

<?php if($message): ?>
<div class="alert"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php
/* ---------- HOME ---------- */
if($page == 'home'):
?>
<div class="hero">
    <h1>Online Car Rental System</h1>
    <p>Book your favourite car easily and quickly.</p>
    <a class="btn" href="car_rental.php?page=cars">View Cars</a>
    <a class="btn green" href="car_rental.php?page=register">Get Started</a>
</div>

<?php
/* ---------- REGISTER ---------- */
elseif($page == 'register'):
?>
<div class="form-box">
<h2>Create Account</h2>
<form method="post">
<input type="text" name="name" placeholder="Full Name" required>
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button class="btn" name="register">Register</button>
</form>
<p>Already have an account? <a href="car_rental.php?page=login">Login</a></p>
</div>

<?php
/* ---------- LOGIN ---------- */
elseif($page == 'login'):
?>
<div class="form-box">
<h2>User Login</h2>
<form method="post">
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button class="btn" name="login">Login</button>
</form>
<p>New user? <a href="car_rental.php?page=register">Register</a></p>
</div>

<?php
/* ---------- ADMIN LOGIN ---------- */
elseif($page == 'admin_login'):
?>
<div class="form-box">
<h2>Admin Login</h2>
<form method="post">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button class="btn" name="admin_login">Login</button>
</form>
<p>Default: admin / admin123</p>
</div>

<?php
/* ---------- CARS ---------- */
elseif($page == 'cars'):
?>
<h2>Available Cars</h2>
<div class="card-grid">
<?php
$cars = $conn->query("SELECT * FROM cars WHERE status='Available' ORDER BY id DESC");
while($car = $cars->fetch_assoc()):
?>
<div class="card">
<?php if($car['image']): ?>
<img class="car-image" src="<?php echo htmlspecialchars($car['image']); ?>">
<?php else: ?>
<div class="car-image" style="display:flex;align-items:center;justify-content:center;font-size:60px;">🚗</div>
<?php endif; ?>
<h2><?php echo htmlspecialchars($car['name']); ?></h2>
<p>Model: <?php echo htmlspecialchars($car['model']); ?></p>
<p><b>₹<?php echo number_format($car['price'],2); ?> / day</b></p>
<?php if(isset($_SESSION['user'])): ?>
<a class="btn" href="car_rental.php?page=book&car_id=<?php echo $car['id']; ?>">Book Now</a>
<?php else: ?>
<a class="btn" href="car_rental.php?page=login">Login to Book</a>
<?php endif; ?>
</div>
<?php endwhile; ?>
</div>

<?php
/* ---------- BOOK ---------- */
elseif($page == 'book'):
if(!isset($_SESSION['user'])) {
    echo "<div class='form-box'><h2>Please login first.</h2></div>";
} else {
$car_id = (int)($_GET['car_id'] ?? 0);
$car = $conn->query("SELECT * FROM cars WHERE id=$car_id")->fetch_assoc();
if(!$car) {
    echo "<div class='form-box'><h2>Car not found.</h2></div>";
} else {
?>
<div class="form-box">
<h2>Book <?php echo htmlspecialchars($car['name']); ?></h2>
<p>Price: ₹<?php echo number_format($car['price'],2); ?> / day</p>
<form method="post">
<input type="hidden" name="car_id" value="<?php echo $car_id; ?>">
<label>Pickup Date</label>
<input type="date" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
<label>Return Date</label>
<input type="date" name="return_date" min="<?php echo date('Y-m-d'); ?>" required>
<button class="btn green" name="book_car">Confirm Booking</button>
</form>
</div>
<?php } } ?>

<?php
/* ---------- MY BOOKINGS ---------- */
elseif($page == 'my_bookings'):
if(!isset($_SESSION['user'])):
?>
<div class="form-box"><h2>Please login first.</h2></div>
<?php else: ?>
<h2>My Bookings</h2>
<table>
<tr>
<th>Car</th><th>Pickup</th><th>Return</th><th>Total</th><th>Status</th>
</tr>
<?php
$uid = (int)$_SESSION['user']['id'];
$q = $conn->query("SELECT b.*,c.name FROM bookings b JOIN cars c ON b.car_id=c.id WHERE b.user_id=$uid ORDER BY b.id DESC");
while($b=$q->fetch_assoc()):
?>
<tr>
<td><?php echo htmlspecialchars($b['name']); ?></td>
<td><?php echo $b['pickup_date']; ?></td>
<td><?php echo $b['return_date']; ?></td>
<td>₹<?php echo number_format($b['total_price'],2); ?></td>
<td><?php echo $b['status']; ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>

<?php
/* ---------- ADMIN DASHBOARD ---------- */
elseif($page == 'admin'):
if(!isset($_SESSION['admin'])):
?>
<div class="form-box"><h2>Admin access required.</h2></div>
<?php else:
$users = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$cars_count = $conn->query("SELECT COUNT(*) c FROM cars")->fetch_assoc()['c'];
$bookings_count = $conn->query("SELECT COUNT(*) c FROM bookings")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='Pending'")->fetch_assoc()['c'];
?>
<h2>Admin Dashboard</h2>
<div class="stat-grid">
<div class="stat"><h2><?php echo $users; ?></h2><p>Users</p></div>
<div class="stat"><h2><?php echo $cars_count; ?></h2><p>Cars</p></div>
<div class="stat"><h2><?php echo $bookings_count; ?></h2><p>Bookings</p></div>
<div class="stat"><h2><?php echo $pending; ?></h2><p>Pending</p></div>
</div>
<?php endif; ?>

<?php
/* ---------- MANAGE CARS ---------- */
elseif($page == 'manage_cars'):
if(!isset($_SESSION['admin'])):
?>
<div class="form-box"><h2>Admin access required.</h2></div>
<?php else: ?>
<h2>Manage Cars</h2>
<div class="form-box">
<h3>Add New Car</h3>
<form method="post">
<input type="text" name="name" placeholder="Car Name" required>
<input type="text" name="model" placeholder="Model / Year" required>
<input type="number" name="price" placeholder="Price per day" required>
<input type="text" name="image" placeholder="Image URL (optional)">
<button class="btn green" name="add_car">Add Car</button>
</form>
</div>

<table>
<tr><th>ID</th><th>Name</th><th>Model</th><th>Price</th><th>Action</th></tr>
<?php
$q=$conn->query("SELECT * FROM cars ORDER BY id DESC");
while($c=$q->fetch_assoc()):
?>
<tr>
<td><?php echo $c['id']; ?></td>
<td><?php echo htmlspecialchars($c['name']); ?></td>
<td><?php echo htmlspecialchars($c['model']); ?></td>
<td>₹<?php echo number_format($c['price'],2); ?></td>
<td><a class="btn red" href="car_rental.php?page=manage_cars&delete_car=<?php echo $c['id']; ?>" onclick="return confirm('Delete this car?')">Delete</a></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>

<?php
/* ---------- BOOKINGS ---------- */
elseif($page == 'bookings'):
if(!isset($_SESSION['admin'])):
?>
<div class="form-box"><h2>Admin access required.</h2></div>
<?php else: ?>
<h2>All Bookings</h2>
<table>
<tr>
<th>ID</th><th>User</th><th>Car</th><th>Pickup</th><th>Return</th><th>Total</th><th>Status</th><th>Action</th>
</tr>
<?php
$q=$conn->query("SELECT b.*,u.name user_name,u.email,c.name car_name
                 FROM bookings b
                 JOIN users u ON b.user_id=u.id
                 JOIN cars c ON b.car_id=c.id
                 ORDER BY b.id DESC");
while($b=$q->fetch_assoc()):
?>
<tr>
<td><?php echo $b['id']; ?></td>
<td><?php echo htmlspecialchars($b['user_name']); ?><br><?php echo htmlspecialchars($b['email']); ?></td>
<td><?php echo htmlspecialchars($b['car_name']); ?></td>
<td><?php echo $b['pickup_date']; ?></td>
<td><?php echo $b['return_date']; ?></td>
<td>₹<?php echo number_format($b['total_price'],2); ?></td>
<td><?php echo $b['status']; ?></td>
<td>
<?php if($b['status']=='Pending'): ?>
<a class="btn green" href="car_rental.php?page=bookings&approve=<?php echo $b['id']; ?>">Approve</a>
<a class="btn red" href="car_rental.php?page=bookings&reject=<?php echo $b['id']; ?>">Reject</a>
<?php else: ?>
-
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>

<?php else: ?>
<div class="form-box"><h2>Page not found.</h2></div>
<?php endif; ?>

</div>

<footer>
Car Rental System | DBMS Project
</footer>

</body>
</html>
