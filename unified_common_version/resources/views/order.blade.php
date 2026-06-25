@include('include.header')
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Order</h4>
                        <a href="{{ url('new_order') }}">
                            {{-- <button type="button" class="btn btn-primary" value="aa" data-toggle="modal" data-target="#" style="float: right;">Add New Order</button> --}}
                        </a>  
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                    <th>No</th>
                                    <th>Order Id</th>
                                    <th>Customer Id</th>
                                    <th>Order Total</th>
                                    <th>Product Id</th>
                                    <th>User Name</th>
                                    {{-- <th>Submissions Id</th> --}}
                                    <th>Time</th>
                                    {{-- <th>Action</th> --}}
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)
    
                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        <td>{{ $data->order_id}}</td>
                                        <td>{{ $data->customerId}}</td>
                                        <td>{{ $data->orderTotal}}</td>
                                        <td>{{ $data->sticky_product_id}}</td>
                                        <td>{{ $data->first_name }} {{ $data->last_name }}</td>
                                        {{-- <th>{{ $data->submissions_id }}</th> --}}
                                        <td>{{ $data->created_at }}</td>
                                        {{-- <td> --}}
                                            {{-- <a href="{{ url('edit_product', $data->product_id) }}" class="btn btn-primary shadow btn-xs sharp me-1"><i
                                                class="fas fa-pencil-alt"></i></a> --}}
                                        {{-- <a href="#" class="btn btn-danger shadow btn-xs sharp"><i
                                                class="fa fa-trash"></i></a> --}}
                                        {{-- </td> --}}
                                        
                                        
    
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