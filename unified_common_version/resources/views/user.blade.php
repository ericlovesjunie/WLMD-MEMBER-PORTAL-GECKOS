@include('include.header')
<style>
    .underline {
        text-decoration: underline;
        color: blue;
    }
</style>
<div class="content-body">
    <!-- row -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">User</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example3" class="display" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Prefix</th>
                                        <th>First Name</th>
                                        <th>Last Price</th>
                                        <th>Phone No</th>
                                        <th>Email</th>
                                        <th>Gender</th>
                                        <th>Date of birth</th>
                                        <th>Address</th>
                                        {{-- <th>Address 2</th> --}}
                                        <th>Zipe Code</th>
                                        <th>City Name</th>
                                        <th>Sate Name</th>
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($table_data as $data)
    
                                    <tr>
                                        <td> {{ $loop->iteration }}</td>
                                        <td>{{ $data->prefix}}</td>
                                        <td><a href="{{ url('user_details', $data->user_id) }}" class="underline">{{ $data->first_name }}</a></td>
                                        <td>{{ $data->last_name}}</td>
                                        <td>{{ $data->phone_number }}</td>
                                        <td>{{ $data->email }}</td>
                                        <td>
                                        @if($data->gender == 1)
                                            Male
                                        @else
                                            Female
                                        @endif
                                        
                                        </td>
                                        <td>{{ $data->date_of_birth}}</td>
                                        <td>{{ $data->address}}</td>
                                        {{-- <td>{{ $data->address2}}</td> --}}
                                        <td>{{ $data->zip_code}}</td>
                                        <td>{{ $data->city_name}}</td>
                                        <td>{{ $data->state_name}}</td>
                                        
                                        
    
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