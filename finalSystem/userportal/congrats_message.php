<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Congratulations!</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #e6f4ea;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .container {
      background: #ffffff;
      padding: 40px;
      max-width: 600px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .emoji {
      font-size: 64px;
      margin-bottom: 20px;
    }

    h1 {
      color: #28a745;
      margin-bottom: 10px;
    }

    p {
      color: #333;
      font-size: 18px;
      line-height: 1.6;
    }

    .btn {
      margin-top: 30px;
      display: inline-block;
      padding: 10px 20px;
      background: #28a745;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
      transition: background 0.3s;
    }

    .btn:hover {
      background: #218838;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="emoji">🎉</div>
    <h1>Congratulations!</h1>
    <p>
      We are thrilled to inform you that you have been <strong>accepted</strong> to our university!<br>
      We look forward to welcoming you as a part of our academic community.
    </p>
    <p>
      Please proceed to complete the next steps in your enrollment.
    </p>
    <a href="userdashboard.php" class="btn">Continue</a>
  </div>
</body>
</html>
