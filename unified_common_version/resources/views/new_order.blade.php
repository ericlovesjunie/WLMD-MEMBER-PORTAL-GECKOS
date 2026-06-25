@include('include.header')
{{-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"> --}}
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<style></style>
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">


            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Add New Order</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-validation">
                            <form method="POST" action="{{ url('') }}" id="addproductForm" enctype="multipart/form-data">
                                @csrf
                                <?php $admin_id = session('SubAdminID'); ?>
                                <div class="row">
                                    {{-- <p></p> --}}
                                    <div>
                                        <h4>Contact</h4>
                                        <hr>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Email</label>
                                            <input type="email" name="email" class="form-control" placeholder="" required>
                                            <input type="hidden" name="card_type" value="1">
                                        </div>
                                    </div>
                                    <p></p>
                                    <div>
                                        <h4>Shipping Address</h4>
                                        <hr>
                                    </div>
                                    {{-- <d/iv> --}}
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">First Name</label>
                                            <input type="text" name="first_name" class="form-control" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Last Name</label>
                                            <input type="text" name="last_name" class="form-control" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Phone</label>
                                            <input type="number" name="phone" class="form-control" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Address</label>
                                            <input type="text" name="address" class="form-control" placeholder="" required>
                                        </div>
                                    </div>

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select Product</label>
                                            <select class="form-control mb-3" name="product_id" id="product_id" required>
                                                <option value="">Select Product</option>
                                                @foreach ($product_data as $benifit_category)

                                                <option value="{{ $benifit_category->product_id }}">
                                                    {{ $benifit_category->product_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">country</label>
                                            <select id="countrySelect" class="form-control mb-3" onchange="fetchStates()">
                                                <option>United States</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select state</label>
                                            <select id="stateSelect" class="form-control mb-3" name="state_name" onchange="handleStateChange(this.value)">
                                                <option value="">Select state</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select city</label>
                                            <select id="citySelect" name="city_name" class="form-control mb-3">
                                                <option value="">Select city</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Postal Code</label>
                                            <input type="number" name="zip_code" class="form-control" placeholder="" required>
                                        </div>
                                    </div>
                                    <p></p>
                                    <div>
                                        <h4>Payment</h4>
                                        <hr>
                                    </div>
                                    <!-- Card Number Input -->
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Card Number</label>
                                            <input type="text" id="cardNumber" class="form-control" placeholder="1111 1111 1111 1111" required oninput="formatCardNumber(this)" maxlength="19">
                                            <input type="hidden" name="card_no" id="card_no">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Name Or Card</label>
                                            <input type="text" name="card_holder_name" class="form-control" placeholder="" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Expiration Date (MM/YY)</label>
                                            <input type="text" id="expirationDate" class="form-control" placeholder="MM/YY" maxlength="5" required oninput="formatExpirationDate(this)">
                                            <!-- Hidden fields for ex_month and ex_year -->
                                            <input type="hidden" name="ex_month" id="ex_month">
                                            <input type="hidden" name="ex_year" id="ex_year">
                                        </div>
                                    </div>
                                    <!-- CVV Code Input -->
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Security Code</label>
                                            <input type="text" name="cvv_no" class="form-control" placeholder="123" required maxlength="3" oninput="validateCVV(this)">
                                        </div>
                                    </div>

                                    <div>
                                        <h4>Billing address</h4>
                                        </h6>
                                        <hr>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <div class="mb-3">
                                            <label class="text-label form-label">Select the address that matches your card or payment method.</label>
                                            <br>
                                            <input type="radio" name="billingSameAsShipping" value="YES" onclick="myFunction(false)" checked>
                                            Same as shipping address
                                            <input type="radio" name="billingSameAsShipping" value="NO" onclick="myFunction(true)">
                                            Use a different billing address
                                        </div>
                                    </div>
                                    <br>

                                    <div id="myDIV" style="display: none;">
                                        <div class="row">
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">First Name</label>
                                                    <input type="text" name="billingFirstName" class="form-control billing-field" placeholder="">
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Last Name</label>
                                                    <input type="text" name="billingLastName" class="form-control billing-field" placeholder="">
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Phone</label>
                                                    <input type="number" name="billingPhone" class="form-control billing-field" placeholder="">
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Address</label>
                                                    <input type="text" name="billingAddress" class="form-control billing-field" placeholder="">
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Country</label>
                                                    <select id="countrySelect" class="form-control mb-3 billing-field" name="billingCountry" onchange="fetchStates()">
                                                        <option>United States</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Select State</label>
                                                    <select id="stateSelect_2" class="form-control mb-3 billing-field" name="billingState" onchange="handleStateChange(this.value)">
                                                        <option value="">Select state</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Select City</label>
                                                    <select id="citySelect_2" name="billingCity" class="form-control mb-3 billing-field">
                                                        <option value="">Select city</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-2">
                                                <div class="mb-3">
                                                    <label class="text-label form-label">Postal Code</label>
                                                    <input type="number" name="billingZip" class="form-control billing-field" placeholder="">
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-lg-12 mb-3">
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    function myFunction(show) {
        const billingDiv = document.getElementById("myDIV");
        const billingFields = document.querySelectorAll(".billing-field");

        if (show) {
            // Show billing fields
            billingDiv.style.display = "block";
            billingFields.forEach(field => {
                field.setAttribute("required", "required");
            });
        } else {
            // Hide billing fields
            billingDiv.style.display = "none";
            billingFields.forEach(field => {
                field.removeAttribute("required");
            });
        }
    }

</script>
<script>
    let allCities = [];
    let selectedState = {
        name: ""
        , state_code: ""
    };

    // Fetch states when page loads
    window.onload = async function fetchStates() {
        try {
            const response = await fetch(
                "https://countriesnow.space/api/v0.1/countries/states", {
                    method: "POST"
                    , headers: {
                        "Content-Type": "application/json"
                    }
                    , body: JSON.stringify({
                        country: "United States"
                    })
                , }
            );
            const data = await response.json();
            const states = data.data.states;
            populateStates(states);
        } catch (error) {
            console.error("Error fetching states:", error);
        }
    };

    // Populate the state dropdown
    function populateStates(states) {
        const stateSelect = document.getElementById("stateSelect");
        states.forEach((state) => {
            const option = document.createElement("option");
            option.value = JSON.stringify({
                name: state.name
                , state_code: state.state_code
            , });
            option.textContent = state.name;
            stateSelect.appendChild(option);
            handleStateChange(option.textContent);
        });
    }

    function populateStates(states) {
        const stateSelect_2 = document.getElementById("stateSelect_2");
        states.forEach((state) => {
            const option = document.createElement("option");
            option.value = JSON.stringify({
                name: state.name
                , state_code: state.state_code
            , });
            option.textContent = state.name;
            stateSelect_2.appendChild(option);
            handleStateChange(option.textContent);
        });
    }

    // Handle state change
    async function handleStateChange(value) {
        const selectedStateObj = JSON.parse(value);
        selectedState = selectedStateObj;

        // Clear the city dropdown first
        const citySelect = document.getElementById("citySelect");
        citySelect.innerHTML = '<option value="">Select city</option>'; // Clear previous options

        const citySelect_2 = document.getElementById("citySelect_2");
        citySelect_2.innerHTML = '<option value="">Select city</option>'; // Clear previous options
        try {
            const response = await fetch(
                "https://countriesnow.space/api/v0.1/countries/state/cities", {
                    method: "POST"
                    , headers: {
                        "Content-Type": "application/json"
                    }
                    , body: JSON.stringify({
                        country: "United States"
                        , state: selectedState.name
                    , })
                , }
            );
            const data = await response.json();

            // Check if the response contains the cities data
            if (data.data && data.data.length > 0) {
                allCities = data.data;
                console.log("Cities fetched:", allCities); // Debug: check fetched cities
                populateCities(allCities); // Populate the cities dropdown
            } else {
                console.error("No cities found for this state.");
            }
        } catch (error) {
            console.error("Error fetching cities:", error);
        }
    }

    // Populate city dropdown
    function populateCities(cities) {
        const citySelect = document.getElementById("citySelect");
        cities.forEach((city) => {
            const option = document.createElement("option");
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
        });
    }

    function populateCities(cities) {
        const citySelect_2 = document.getElementById("citySelect_2");
        cities.forEach((city) => {
            const option = document.createElement("option");
            option.value = city;
            option.textContent = city;
            citySelect_2.appendChild(option);
        });
    }

</script>

<script>
    function formatExpirationDate(input) {
        // Remove any non-digit characters
        let value = input.value.replace(/\D/g, '');

        // Format the input as MM/YY
        if (value.length >= 3) {
            input.value = value.slice(0, 2) + '/' + value.slice(2, 4);
        } else {
            input.value = value;
        }

        // Ensure only MM/YY format remains
        if (input.value.length > 5) {
            input.value = input.value.slice(0, 5);
        }

        // Update hidden fields
        const parts = input.value.split('/');
        document.getElementById('ex_month').value = parts[0] || '';
        document.getElementById('ex_year').value = parts[1] || '';
    }

</script>


<script>
    // Format Card Number to Show as 1111 1111 1111 1111
    function formatCardNumber(input) {
        let value = input.value.replace(/\D/g, ''); // Remove non-digit characters
        value = value.replace(/(\d{4})(?=\d)/g, '$1 '); // Add space every 4 digits
        input.value = value;

        // Update hidden input for database
        document.getElementById('card_no').value = value.replace(/\s/g, ''); // Remove spaces
    }

    // Validate CVV Input (3 Digits Only)
    function validateCVV(input) {
        input.value = input.value.replace(/\D/g, ''); // Allow only digits
        if (input.value.length > 3) {
            input.value = input.value.slice(0, 3); // Restrict to 3 digits
        }
    }

</script>
@include('include.footer')
{{-- @include('include.message') --}}
