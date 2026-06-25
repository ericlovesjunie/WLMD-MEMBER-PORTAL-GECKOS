<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Error</title>
    <style>
      @font-face {
        font-family: "Gibson";
        font-weight: 700;
        src: url(./gibson/fonnts.com-Gibson_Bold.otf) format("opentype");
      }
      @font-face {
        font-family: "Gibson_Light";
        /* font-weight: 500; */
        src: url(./gibson/fonnts.com-Gibson_Light.otf) frmat("opentype");
      }

      body {
        background: #ffefba;
        background: -webkit-linear-gradient(to right, #ffefba, #ffffff);
        background: linear-gradient(to right, #ffefba, #ffffff);
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        text-align: center;
        height: 100vh;
        margin: 0;
        position: relative;
        overflow: hidden;
      }

      .error_h1 {
        font-size: 10rem;
        font-weight: 700;
        color: black;
        font-family: "Gibson", sans-serif;
      }

      .error_h3 {
        display: block;
        width: 70%;
        margin: 2rem auto;
        min-width: 300px;
        font-size: 1.2rem;
        text-transform: none;
        font-weight: 700;
        color: #555555;
        font-family: "Gibson_Light", sans-serif;
      }

      header {
        position: absolute;
        top: 0;
        background-color: #000;
        font-weight: 700;
        width: 100%;
        padding: 1rem 3rem;
      }

      .success_div {
        line-height: 25px;
      }

      .footer {
        background-color: #2b2d31;
        color: #fff;
        position: absolute;
        bottom: 0;
        width: 100%;
        text-align: start;
        padding: 1rem 1rem;
        border-top: 1px solid #333;
        color: #b9b9b9;
      }

      .footer-titel {
        position: relative;
        left: 25px;
        font-size: 16px;
        font-family: "Gibson_Light", sans-serif;
        color: #555;
      }
      @media screen and (max-width: 720px) {
        .sucesss_h1 {
          font-size: 4rem;
        }

        .sucesss_h3 {
          width: 90%;
          font-size: 1rem;
          line-height: 1.4rem;
        }
        header {
          padding: 0.5rem 1.5rem;
        }

        .footer {
          padding: 0.5rem 1.5rem;
          font-size: 0.9rem;
        }

        .footer-titel {
          font-size: 0.8rem;
        }
      }
    </style>
  </head>

  <body>
    <!-- <header>
      <center>
        <img
          style="
            max-height: 60px;
            margin: auto;
            max-width: 160px;
            object-fit: contain;
          "
          src="./logo-levalup.svg"
          alt=""
        />
      </center>
    </header> -->
    <div class="success_div">
      <h1 class="error_h1">404</h1>
      <h3 class="error_h3">(Page not found)</h3>
    </div>
    <!-- <div class="footer">
      <p class="footer-titel">© Mensrx 2024 All Rights Reserved</p>
    </div> -->
  </body>
</html>
