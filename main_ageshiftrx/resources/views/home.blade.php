@include('include.header')

<div class="content-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-6">
                <a href="{{ url('product') }}">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Total Product</h4>
                    </div>
                    <div class="card-body">
                        <h4>{{ $product }}</h4>
                    </div>
                </div>
                </a>
            </div>
        </div>
    </div>
</div>
{{-- @include('include.message') --}}



@include('include.footer')
