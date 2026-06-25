@include('include.header')

<div class="content-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">User Details</h4>

                        @if(isset($submissions) && $submissions !== null)
                        @if($is_upload == 1)
                        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#exampleModalCenter">Send File Upload Request</button>
                        @else
                        <button type="button" class="btn btn-secondary" disabled>Send File Upload Request</button>
                        @endif
                        @else
                     
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="admin-details">
                            @foreach(['first_name', 'last_name', 'date_of_birth', 'phone_number', 'email', 'address', 'address2', 'zip_code', 'city_name', 'state_name'] as $field)
                            <div class="detail-row">
                                <span class="detail-label">{{ ucwords(str_replace('_', ' ', $field)) }}:</span>
                                <span class="detail-value">
                                    {{ is_array($table_data->$field) ? json_encode($table_data->$field) : $table_data->$field }}
                                </span>
                            </div>
                            @endforeach
                        </div>

                        <!-- Modal -->
                        <div class="modal fade" id="exampleModalCenter" tabindex="-1" aria-labelledby="exampleModalCenterLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalCenterLabel">Send File Upload Request</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="{{ route('send_update_file_re') }}" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" value="{{ $table_data->user_id }}" name="user_id">
                                            <div class="mb-3">
                                                <label for="Categories" class="form-label">Send File Upload Request</label>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(isset($submissions) && $submissions !== null)
                        <div class="submissions-section mt-4">
                            <h5 class="section-title">Submissions Details</h5>
                            <div class="detail-row">
                                <span class="detail-label">Form Id:</span>
                                <span class="detail-value">{{ is_array($submissions->form_id) ? json_encode($submissions->form_id) : $submissions->form_id }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Submissions Id:</span>
                                <span class="detail-value">{{ is_array($submissions->submissions_id) ? json_encode($submissions->submissions_id) : $submissions->submissions_id }}</span>
                            </div>
                        </div>

                        <div class="answers-section mt-4">
                            <h5 class="section-title">Question Answers: Cycle 1</h5>
                            <div class="answer-list">
                                @foreach($submissions->answers as $questionId => $answer)
                                @if(isset($answer['type']) && $answer['type'] === 'control_pagebreak')
                                @continue
                                @elseif(isset($answer['answer']) && $answer['answer'] !== '')
                                <div class="answer-item">
                                    <div class="answer-question">
                                        {{ $answer['text'] ?? 'No question text' }}
                                    </div>
                                    <div class="answer-content">
                                        @if(is_array($answer['answer']))
                                        {{ implode(', ', $answer['answer']) }}
                                        @else
                                        {{ $answer['answer'] }}
                                        @endif
                                    </div>
                                </div>
                                @endif
                                @endforeach
                            </div>
                        </div>
                        @else
                        <div class="alert alert-info mt-3">
                            No submission data available.
                        </div>
                        @endif

                        @if(isset($webhook) && $webhook !== null && isset($webhook->webhook_data['pretty']))
                        <div class="webhook-section mt-4">
                            <h5 class="section-title">Webhook Data</h5>
                            <div class="webhook-data">
                                @php
                                    $prettyData = explode(', ', $webhook->webhook_data['pretty']);
                                @endphp
                                @foreach($prettyData as $item)
                                    @php
                                        $parts = explode(':', $item, 2);
                                    @endphp
                                    @if(count($parts) == 2)
                                        <div class="detail-row">
                                            <span class="detail-label">{{ trim($parts[0]) }}:</span>
                                            <span class="detail-value">{{ trim($parts[1]) }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @else
                        {{-- <div class="alert alert-info mt-3">
                            No webhook data available.
                        </div> --}}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* ... (previous styles remain unchanged) ... */

    .webhook-section {
        margin-top: 30px;
    }

    .webhook-data {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .webhook-data .detail-row {
        padding: 10px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .webhook-data .detail-row:last-child {
        border-bottom: none;
    }

    .webhook-data .detail-label {
        font-weight: 600;
        color: #495057;
        margin-right: 10px;
    }

    .webhook-data .detail-value {
        color: #6c757d;
        word-break: break-all;
    }


</style>

<style>
    .card {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .card-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
    }

    .card-title {
        color: #333;
        font-weight: 600;
        margin: 0;
    }

    .card-body {
        padding: 20px;
        background-color: #ffffff;
    }

    .admin-details,
    .submissions-section {
        margin-bottom: 20px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .detail-label {
        font-weight: 600;
        color: #495057;
    }

    .detail-value {
        color: #6c757d;
    }

    .section-title {
        margin-bottom: 15px;
        color: #343a40;
        font-weight: 600;
    }

    .answer-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .answer-item {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .answer-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .answer-question {
        font-weight: 600;
        color: #495057;
        margin-bottom: 10px;
    }

    .answer-content {
        color: #6c757d;
        white-space:normal;
    }

    .alert-info {
        background-color: #e1f5fe;
        border-color: #b3e5fc;
        color: #0288d1;
        text-align: center;
    }

    @media screen and (max-width: 768px) {
        .answer-list {
            grid-template-columns: 1fr;
        }
    }

</style>

@include('include.footer')