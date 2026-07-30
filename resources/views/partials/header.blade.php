<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">

    <div class="container">

        <a class="navbar-brand fw-bold" href="{{ url('/') }}">

            <img src="{{ asset('images/logo.png') }}"
                 width="45"
                 class="me-2"
                 alt="Sai Consulting">

            Sai Consulting

        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainMenu">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse"
             id="mainMenu">

            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link active"
                       href="/">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="#">About</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="#">Services</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="#">Status</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="#">Contact</a>
                </li>

            </ul>

            <a href="https://wa.me/919913793876"
               target="_blank"
               class="btn btn-warning ms-lg-3">

                WhatsApp

            </a>

        </div>

    </div>

</nav>