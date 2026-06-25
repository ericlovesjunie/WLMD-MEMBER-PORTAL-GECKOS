@include('include.header')
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Product</h4>
                        {{-- <a href="{{ url('add_new_product') }}">
                            <button type="button" class="btn btn-primary" value="aa" data-toggle="modal" data-target="#" style="float: right;">Add New Product</button>
                        </a>   --}}
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                    <th>No</th>
                                    <th>Product Id</th>
                                    <th>Product Name</th>
                                    <th>Product Price</th>
                                    {{-- <th>Form URL</th> --}}
                                    {{-- <th>Form Id</th> --}}
                                    <th>Sticky Product Id</th>
                                    <th>Checkout Url</th>
                                    <th>Action</th>
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)
    
                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        <td>{{ $data->product_id}}</td>
                                        <td>{{ $data->product_name}}</td>
                                        <td>{{ $data->product_price}}</td>
                                        {{-- <td>{{ $data->form_url}}</td> --}}
                                        {{-- <td>{{ $data->form_id}}</td> --}}
                                        <td>{{ $data->sticky_product_id}}</td>
                                        <td>                                        <a href="{{ $data->checkout_url }}" class="underline">{{ $data->checkout_url}}
                                            {{-- {{ $data->checkout_url }} --}}
                                        </td>
                                        <td>
                                            <a href="{{ url('edit_product', $data->product_id) }}" class="btn btn-primary shadow btn-xs sharp me-1"><i
                                                class="fas fa-pencil-alt"></i></a>
                                        {{-- <a href="#" class="btn btn-danger shadow btn-xs sharp"><i
                                                class="fa fa-trash"></i></a> --}}
                                        </td>
                                        
                                        
    
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
          
        </div>
    </div>
</div>

@include('include.footer')
@include('include.message')