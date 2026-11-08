<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TailorTrack | Customers</title>

    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="app-layout">

        <aside class="sidebar">
            <div class="brand">
                <h2>TailorTrack</h2>
                <p>Your studio, in sync.</p>
            </div>

            <nav class="navigation" aria-label="Main navigation">
                <a href="index.php">Overview</a>

                <button type="button" disabled>Orders</button>

                <a href="customers.php" class="active"
                   aria-current="page">
                    Customers
                </a>

                <button type="button" disabled>Fittings</button>
                <button type="button" disabled>Payments</button>
                <button type="button" disabled>Studio settings</button>
            </nav>

            <p class="sidebar-note">
                Every measurement. Every order. One workspace.
            </p>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <p class="eyebrow">WORKSPACE / CUSTOMERS</p>
                <h1>Customers</h1>
                <p class="description">
                    Keep your customer details organised.
                </p>
            </header>

            <section class="form-panel">
                <h2>Add a customer</h2>

                <p id="form-notice" class="description">
                    Form preview only. Saving will be enabled
                    after the database is connected.
                </p>

                <form aria-describedby="form-notice">
                    <div class="form-grid">

                        <div class="form-field">
                            <label for="customer-name">
                                Full name *
                            </label>

                            <input
                                type="text"
                                id="customer-name"
                                name="name"
                                placeholder="Enter customer name"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="customer-phone">
                                Phone number *
                            </label>

                            <input
                                type="tel"
                                id="customer-phone"
                                name="phone"
                                placeholder="Example: 0771234567"
                                maxlength="30"
                                required
                            >
                        </div>

                        <div class="form-field full-width">
                            <label for="customer-notes">
                                Notes (optional)
                            </label>

                            <textarea
                                id="customer-notes"
                                name="notes"
                                rows="4"
                                maxlength="5000"
                                placeholder="Preferences or special instructions"
                            ></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="reset" class="button-secondary">
                            Clear form
                        </button>

                        <button type="button"
                                class="button-primary" disabled>
                            Save customer
                        </button>
                    </div>
                </form>
            </section>
        </main>

    </div>

</body>
</html>