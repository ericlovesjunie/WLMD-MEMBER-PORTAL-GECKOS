@include('include.header')

<div class="content-body">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Cases Details</h4>
                        <div class="d-flex align-items-center">
                            <input type="text" id="searchInput" class="form-control me-2" placeholder="Search questions...">
                            @if($is_upload == 1)
                                <button type="button" class="btn btn-primary mb-2 send-request-btn" data-bs-toggle="modal" data-bs-target="#exampleModalCenter">Send FULL body File Upload Request</button>
                            @else
                                <button type="button" class="btn btn-secondary" disabled>Send FULL body File Upload Request</button>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Modal -->
                    <div class="modal fade" id="exampleModalCenter">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">File Upload Request</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="{{ route('send_update_full_bodey_img') }}" enctype="multipart/form-data">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="Categories" class="form-label">Send File Upload Request</label>
                                            <input type="hidden" value="{{ $is_case->user_id }}" name="user_id">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" class="btn btn-primary">Send Request</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="admin-details">
                            @if(isset($questions) && count($questions) > 0)
                                <div class="answer-list" id="answerList">
                                    @foreach($questions as $question)
                                        <div class="answer-item">
                                            <div class="answer-question">{{ $question['question'] }}</div>
                                            <div class="answer-content">{!! nl2br(e($question['answer'])) !!}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="alert alert-info text-center">
                                    No questions available.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .container-fluid {
        max-width: 1200px;
        margin: auto;
        padding: 20px;
    }
    /* .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
        transition: background-color 0.3s, box-shadow 0.3s;
    } */
    .card {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        margin-bottom: 20px;
        overflow: hidden;
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
    .form-control {
        max-width: 300px;
        margin-right: 10px;
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
        white-space: pre-wrap;
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
        .form-control {
            width: 100%;
            margin-bottom: 10px;
        }
        .btn {
            width: 100%;
        }
    }
</style>

<script>
    document.getElementById('searchInput').addEventListener('input', function() {
        let searchValue = this.value.toLowerCase();
        let answerItems = document.querySelectorAll('.answer-item');

        answerItems.forEach(function(item) {
            let questionText = item.querySelector('.answer-question').textContent.toLowerCase();
            let answerText = item.querySelector('.answer-content').textContent.toLowerCase();
            
            if (questionText.includes(searchValue) || answerText.includes(searchValue)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
</script>

@include('include.footer')