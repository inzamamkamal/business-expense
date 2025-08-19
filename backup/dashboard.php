<?php
session_start();

if (!isset($_SESSION['username'])) {
  header('Location: index.php');
  exit();
}

include 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BTS DISC 2.0 - Dashboard</title>
  <style>
    :root {
      --bg: #181818;
      --text: #fff;
      --shadow: rgba(0, 0, 0, 0.2);
      --shadow-hover: rgba(0, 0, 0, 0.3);
      --border-radius: 16px;
      --tile-padding: 24px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background-color: var(--bg);
      color: var(--text);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px;
      min-height: 100vh;
    }

    h1 {
      text-align: center;
      margin: 10px auto 20px;
      font-size: 1.8rem;
      color: #ffc107;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      width: 100%;
      max-width: 1200px;
    }

    .tile {
      background-color: #333;
      border-radius: var(--border-radius);
      padding: var(--tile-padding);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      text-decoration: none;
      color: var(--text);
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px var(--shadow);
    }

    .tile:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 18px var(--shadow-hover);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .tile span.title {
      font-size: 0.95rem;
      font-weight: 600;
      opacity: 0.9;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .tile span.text {
      font-size: 1.1rem;
      margin: 10px 0;
      line-height: 1.4;
    }

    .tile span.icon {
      font-size: 2rem;
      align-self: flex-end;
    }

    /* ADMIN COLOR SCHEME */
    .tile.green { background-color: #6b9940; }
    .tile.blue { background-color: #3b5998; }
    .tile.lightblue { background-color: #00aced; }
    .tile.brown { background-color: #a18f60; }
    .tile.darkbrown { background-color: #5a4044; }
    .tile.littlegreen { background-color: #4b7f5b; }
    .tile.logout { background-color: #a11f1f; }

    /* USER COLOR SCHEME */
    body.user-dashboard .tile:not(.logout) {
      background: linear-gradient(135deg, #2c3e50, #4a6491);
    }
    
    body.user-dashboard .tile.logout {
      background: linear-gradient(135deg, #8B0000, #c0392b);
    }
    
    body.user-dashboard .tile:hover {
      filter: brightness(115%);
    }
    
    body.user-dashboard .dashboard-title {
      color: #4fc3f7;
      text-shadow: 0 0 10px rgba(79, 195, 247, 0.5);
    }
    
    body.user-dashboard .tile span.icon {
      opacity: 0.8;
    }

    @media (max-width: 768px) {
      h1 {
        font-size: 1.5rem;
      }
      .tile span.icon {
        font-size: 1.5rem;
      }
    }

    @media (max-width: 480px) {
      h1 {
        font-size: 1.3rem;
      }
      .tile {
        padding: 16px;
      }
      .tile span.title {
        font-size: 0.85rem;
      }
      .tile span.text {
        font-size: 1rem;
      }
    }
    .dashboard-title {
      font-size: 1.8rem;
      text-align: center;
      font-family: 'Brush Script MT', cursive;
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="<?php echo ($_SESSION['role'] === 'user') ? 'user-dashboard' : ''; ?>">

  <h1 class="dashboard-title">BTS DISC 2.0 - Dashboard</h1>

  <div class="grid">

    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') : ?>
      <a href="public/add_staff.php" class="tile green">
        <span class="title">STAFF</span>
        <span class="text">Manage staff records</span>
        <span class="icon">👥</span>
      </a>
    <?php endif; ?>

    <a href="public/booking.php" class="tile blue">
      <span class="title">PARTY BOOKINGS</span>
      <span class="text">Plan celebrations here</span>
      <span class="icon">🎉</span>
    </a>

    <a href="public/attendence.php" class="tile lightblue">
      <span class="title">ATTENDANCE</span>
      <span class="text">Track staff attendance</span>
      <span class="icon">📅</span>
    </a>

    <a href="public/expense.php" class="tile brown">
      <span class="title">EXPENSE</span>
      <span class="text">Record business expenses</span>
      <span class="icon">💸</span>
    </a>

    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') : ?>
      <a href="public/add_income.php" class="tile darkbrown">
        <span class="title">INCOME</span>
        <span class="text">Add income & view reports</span>
        <span class="icon">💰</span>
      </a>

      <a href="public/settlement.php" class="tile littlegreen">
        <span class="title">SETTLEMENT</span>
        <span class="text">Handle staff settlements</span>
        <span class="icon">🧾</span>
      </a>
    <?php endif; ?>

    <a href="logout.php" class="tile logout">
      <span class="title">LOGOUT</span>
      <span class="text">Exit the dashboard</span>
      <span class="icon">🚪</span>
    </a>

  </div>
</body>
</html>