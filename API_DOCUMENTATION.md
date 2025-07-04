# AxPEP_Backend API Documentation

## Table of Contents

1. [Overview](#overview)
2. [Base URL and Authentication](#base-url-and-authentication)
3. [API Endpoints](#api-endpoints)
4. [Data Models](#data-models)
5. [Service Classes](#service-classes)
6. [Background Jobs](#background-jobs)
7. [Utility Functions](#utility-functions)
8. [Error Handling](#error-handling)
9. [Usage Examples](#usage-examples)

## Overview

AxPEP_Backend is a RESTful API system built with Laravel for bioinformatics peptide sequence analysis. It provides multiple analysis services including:

- **AmPEP**: Antimicrobial peptide analysis
- **AcPEP**: Anticancer peptide analysis  
- **BESTox**: Toxicology analysis
- **SSL-GCN**: SSL-based Graph Convolutional Network analysis
- **Ecotoxicology**: Environmental toxicology analysis
- **HemoPep**: Hemolytic peptide analysis

The system uses asynchronous task processing for compute-intensive analysis and provides comprehensive task management capabilities.

## Base URL and Authentication

**Base URL**: `{domain}/api/v1/`

**Authentication**: Currently no authentication required for API endpoints.

**Content-Type**: `application/json` for most endpoints, `multipart/form-data` for file uploads.

## API Endpoints

### 1. Task Management APIs

#### Get Task by ID
```http
GET /api/v1/axpep/tasks/{id}
```

**Description**: Retrieve detailed information about a specific task, including analysis results.

**Parameters**:
- `id` (path parameter): Task UUID

**Response**: Task object with analysis results based on application type.

**Example Response**:
```json
{
  "status": true,
  "data": {
    "id": "uuid-string",
    "email": "user@example.com",
    "action": "finished",
    "application": "ampep",
    "classifications": [...],
    "scores": [...],
    "created_at": "2023-01-01T00:00:00Z"
  }
}
```

#### Get Tasks by Email
```http
GET /api/v1/axpep/emails/{email}/tasks
```

**Description**: Retrieve all tasks associated with a specific email address.

**Parameters**:
- `email` (path parameter): User email address

**Response**: Array of task objects.

#### Download Classification Results
```http
GET /api/v1/axpep/tasks/{id}/classification/download
```

**Description**: Download classification results as CSV file.

**Parameters**:
- `id` (path parameter): Task UUID

**Response**: CSV file download.

#### Download Prediction Scores
```http
GET /api/v1/axpep/tasks/{id}/score/download
```

**Description**: Download prediction scores as CSV file.

**Parameters**:
- `id` (path parameter): Task UUID

**Response**: CSV file download.

#### Download Complete Results
```http
GET /api/v1/axpep/tasks/{id}/result/download
```

**Description**: Download complete analysis results as CSV file.

**Parameters**:
- `id` (path parameter): Task UUID

**Response**: CSV file download.

### 2. Analytics APIs

#### Count Tasks by Days
```http
GET /api/v1/axpep/analysis/count/tasks
```

**Description**: Get task count statistics for the last N days.

**Query Parameters**:
- `days` (optional): Number of days to analyze (default: 7)

#### Count Locations by Days
```http
GET /api/v1/axpep/analysis/count/tasks/locations
```

**Description**: Get unique IP location count for the last N days.

#### Count Methods Usage
```http
GET /api/v1/axpep/analysis/count/method
```

**Description**: Get usage statistics for each analysis method.

### 3. AmPEP (Antimicrobial Peptide) Analysis APIs

#### Create AmPEP Task with File Upload
```http
POST /api/v1/ampep/tasks/file
```

**Description**: Create a new AmPEP analysis task by uploading a FASTA file.

**Request Body** (multipart/form-data):
```json
{
  "email": "user@example.com",
  "description": "Analysis description",
  "file": "FASTA file",
  "methods": {
    "ampep": true,
    "deepampep30": false,
    "rfampep30": true
  }
}
```

**Validation Rules**:
- `email`: Required, valid email format
- `file`: Required, must be a file
- `methods`: Required, object with boolean values
- `description`: Optional string

#### Create AmPEP Task with Text Input
```http
POST /api/v1/ampep/tasks/textarea
```

**Description**: Create a new AmPEP analysis task by providing FASTA sequences as text.

**Request Body**:
```json
{
  "email": "user@example.com",
  "description": "Analysis description",
  "fasta": ">seq1\nMKLLILLLCLAFLPLVLG\n>seq2\nAKLLILLLCLAFLPLVLG",
  "methods": {
    "ampep": true,
    "deepampep30": true,
    "rfampep30": false
  }
}
```

#### Create AmPEP Task with Codon Translation
```http
POST /api/v1/ampep/tasks/codon
```

**Description**: Create AmPEP analysis task with DNA sequence translation using specific codon table.

**Request Body** (multipart/form-data):
```json
{
  "email": "user@example.com",
  "description": "Analysis description",
  "file": "DNA FASTA file",
  "codon": "standard",
  "methods": {
    "ampep": true,
    "deepampep30": true
  }
}
```

### 4. AcPEP (Anticancer Peptide) Analysis APIs

#### Create AcPEP Task with File Upload
```http
POST /api/v1/acpep/tasks/file
```

**Description**: Create anticancer peptide analysis task by uploading FASTA file.

**Request Body**: Same format as AmPEP file upload.

#### Create AcPEP Task with Text Input
```http
POST /api/v1/acpep/tasks/textarea
```

**Description**: Create anticancer peptide analysis task with text input.

**Request Body**: Same format as AmPEP text input.

#### Create AcPEP Task with Codon Translation
```http
POST /api/v1/acpep/tasks/codon
```

**Description**: Create AcPEP analysis with DNA sequence translation.

**Request Body**: Same format as AmPEP codon analysis.

### 5. BESTox (Toxicology) Analysis APIs

#### Create BESTox Task with File Upload
```http
POST /api/v1/bestox/tasks/file
```

**Description**: Create toxicology analysis task by uploading FASTA file.

#### Create BESTox Task with Text Input
```http
POST /api/v1/bestox/tasks/textarea
```

**Description**: Create toxicology analysis task with text input.

### 6. SSL-GCN Analysis APIs

#### Create SSL-GCN Task with File Upload
```http
POST /api/v1/ssl-gcn/tasks/file
```

**Description**: Create SSL-based Graph Convolutional Network analysis task.

#### Create SSL-GCN Task with Text Input
```http
POST /api/v1/ssl-gcn/tasks/textarea
```

**Description**: Create SSL-GCN analysis task with text input.

### 7. Ecotoxicology Analysis APIs

#### Create Ecotoxicology Task with File Upload
```http
POST /api/v1/ecotoxicology/tasks/file
```

**Description**: Create environmental toxicology analysis task.

#### Create Ecotoxicology Task with Text Input
```http
POST /api/v1/ecotoxicology/tasks/textarea
```

**Description**: Create ecotoxicology analysis task with text input.

### 8. HemoPep Analysis APIs

#### Create HemoPep Task with File Upload
```http
POST /api/v1/hemopep/tasks/file
```

**Description**: Create hemolytic peptide analysis task.

#### Create HemoPep Task with Text Input
```http
POST /api/v1/hemopep/tasks/textarea
```

**Description**: Create HemoPep analysis task with text input.

### 9. Codon APIs

#### Get All Codons
```http
GET /api/v1/axpep/codons/all
```

**Description**: Retrieve all available codon tables for DNA translation.

**Response**: Array of codon table objects.

## Data Models

### Tasks Model

**File**: `app/Models/Tasks.php`

**Fields**:
- `id` (string): UUID primary key
- `email` (string): User email address
- `action` (string): Task status (`running`, `finished`, `failed`)
- `source` (string): Input source type
- `description` (string): Task description
- `application` (string): Analysis type
- `ip` (string): Client IP address (hidden from API responses)
- `created_at` (datetime): Task creation timestamp
- `updated_at` (datetime): Last update timestamp

**Relationships**:
- `details()`: Has many TasksMethods

### TasksMethods Model

**File**: `app/Models/TasksMethods.php`

**Fields**:
- `id` (string): UUID primary key
- `task_id` (string): Foreign key to Tasks
- `method` (string): Analysis method name
- `classification` (text): Classification results
- `prediction_score` (text): Prediction scores
- `created_at` (datetime): Creation timestamp
- `updated_at` (datetime): Update timestamp

### Codons Model

**File**: `app/Models/Codons.php`

**Fields**:
- `id` (integer): Primary key
- `name` (string): Codon table name
- `description` (string): Codon table description

## Service Classes

### Base Service Interface

**File**: `app/Services/BaseServicesInterface.php`

All service classes implement this interface requiring:
- `dataValidation($request, $method)`: Validate request data

### AmPEPServices

**File**: `app/Services/AmPEPServices.php`

**Pattern**: Singleton

**Key Methods**:
- `getInstance()`: Get singleton instance
- `dataValidation($request, $method)`: Validate request based on method type
- `createNewTaskByFile(Request $request)`: Process file upload task creation
- `createNewTaskByTextarea(Request $request)`: Process text input task creation
- `createNewTaskByFileAndCodon(Request $request)`: Process codon translation task
- `insertTasksMethods($request, $data)`: Insert analysis methods for task
- `finishedTask($taskID)`: Mark task as finished and process results
- `failedTask($taskID)`: Mark task as failed

### TasksServices

**File**: `app/Services/TasksServices.php`

**Pattern**: Singleton

**Key Methods**:
- `getInstance()`: Get singleton instance
- `responseSpecify(Request $request)`: Get specific task with results
- `responseSpecifyTaskByEmail(Request $request)`: Get tasks by email
- `downloadSpecifyClassification($request)`: Download classification file
- `downloadSpecifyPredictionScore($request)`: Download score file
- `downloadSpecifyResult($request)`: Download result file
- `countDistinctIpNDays($request)`: Count unique IPs for N days
- `countTasksNDays($request)`: Count tasks for N days
- `countEachMethods($request)`: Count method usage statistics

### Other Service Classes

Similar patterns exist for:
- `AcPEPServices`: Anticancer peptide analysis
- `BESToxServices`: Toxicology analysis
- `EcotoxicologyServices`: Environmental toxicology
- `HemoPepServices`: Hemolytic peptide analysis
- `SSLBESToxServices`: SSL-based toxicology analysis
- `CodonsServices`: Codon table management

## Background Jobs

### AmPEPJob

**File**: `app/Jobs/AmPEPJob.php`

**Timeout**: 7200 seconds (2 hours)

**Functionality**:
- Processes AmPEP analysis methods based on request parameters
- Runs different analysis algorithms (AmPEP, DeepAmPEP30, RFAmPEP30)
- Updates task status upon completion or failure

**Methods**:
- `__construct($task, $request)`: Initialize with task and request data
- `handle()`: Execute the analysis job
- `failed(?\Throwable $exception)`: Handle job failure

### Other Job Classes

Similar structure for:
- `AcPEPJob`: Anticancer peptide analysis
- `BESToxJob`: Toxicology analysis
- `EcotoxicologyJob`: Environmental toxicology
- `HemoPepJob`: Hemolytic peptide analysis
- `SSLBESToxJob`: SSL-based analysis
- `CodonJob`: DNA sequence translation

## Utility Functions

### FileUtils

**File**: `app/Utils/FileUtils.php`

**Key Functions**:
- `createResultFile($path, $methods)`: Create result file structure
- `insertSequencesAndHeaderOnResult($path, $methods, $type)`: Add sequences to result files
- `writeAmPEPResultFile($taskID, $methods)`: Write final AmPEP results
- `writeAcPEPResultFile($taskID, $methods)`: Write final AcPEP results

### TaskUtils

**File**: `app/Utils/TaskUtils.php`

**Key Functions**:
- `createTaskFolder($task)`: Create folder structure for task
- `runAmPEPTask($task)`: Execute AmPEP analysis
- `runDeepAmPEP30Task($task)`: Execute DeepAmPEP30 analysis
- `runRFAmPEP30Task($task)`: Execute RFAmPEP30 analysis

### ResponseUtils

**File**: `app/Utils/ResponseUtils.php`

**Key Functions**:
- `success()`: Return success message
- `fail()`: Return failure message
- `validatorErrorMessage($validator)`: Format validation errors
- `exceptionMessage($exception)`: Format exception messages

### RequestUtils

**File**: `app/Utils/RequestUtils.php`

**Key Functions**:
- `addTaskID($request)`: Add task ID to request
- `addEmail($request)`: Add email to request
- `addSpecificInput($data)`: Add specific input data to request

## Error Handling

### Validation Errors

The API uses Laravel's validation system. Common validation rules:

**File Upload Rules**:
```php
'email' => 'required|email',
'file' => 'required|file',
'methods' => 'required'
```

**Text Input Rules**:
```php
'email' => 'required|email',
'fasta' => 'required|string',
'methods' => 'required'
```

### Error Response Format

```json
{
  "status": false,
  "message": {
    "field_name": ["Error message"]
  },
  "errorCode": 1000
}
```

### Common Error Codes

- `1000`: Validation error
- Task status codes: `running`, `finished`, `failed`

## Usage Examples

### Example 1: Create AmPEP Analysis Task with File

```bash
curl -X POST \
  -H "Content-Type: multipart/form-data" \
  -F "email=researcher@university.edu" \
  -F "description=Antimicrobial peptide analysis for new compounds" \
  -F "file=@sequences.fasta" \
  -F "methods[ampep]=true" \
  -F "methods[deepampep30]=true" \
  -F "methods[rfampep30]=false" \
  http://api.example.com/api/v1/ampep/tasks/file
```

### Example 2: Create Analysis Task with Text Input

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "email": "researcher@university.edu",
    "description": "Quick analysis of two peptides",
    "fasta": ">peptide1\nMKLLILLLCLAFLPLVLG\n>peptide2\nAKLLILLLCLAFLPLVLG",
    "methods": {
      "ampep": true,
      "deepampep30": true
    }
  }' \
  http://api.example.com/api/v1/ampep/tasks/textarea
```

### Example 3: Check Task Status

```bash
curl -X GET \
  http://api.example.com/api/v1/axpep/tasks/uuid-task-id
```

### Example 4: Download Results

```bash
curl -X GET \
  -o classification_results.csv \
  http://api.example.com/api/v1/axpep/tasks/uuid-task-id/classification/download
```

### Example 5: Get Tasks by Email

```bash
curl -X GET \
  http://api.example.com/api/v1/axpep/emails/researcher@university.edu/tasks
```

### Example 6: Get Analytics Data

```bash
# Get task count for last 30 days
curl -X GET \
  "http://api.example.com/api/v1/axpep/analysis/count/tasks?days=30"

# Get method usage statistics
curl -X GET \
  http://api.example.com/api/v1/axpep/analysis/count/method
```

### Example 7: JavaScript/TypeScript Integration

```typescript
interface TaskResponse {
  status: boolean;
  data: {
    id: string;
    email: string;
    action: 'running' | 'finished' | 'failed';
    application: string;
    classifications?: any[];
    scores?: any[];
    created_at: string;
  };
}

class AxPEPClient {
  private baseUrl = 'http://api.example.com/api/v1';

  async createAmPEPTask(
    email: string,
    fasta: string,
    methods: { [key: string]: boolean },
    description?: string
  ): Promise<TaskResponse> {
    const response = await fetch(`${this.baseUrl}/ampep/tasks/textarea`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email,
        fasta,
        methods,
        description,
      }),
    });

    return response.json();
  }

  async getTask(taskId: string): Promise<TaskResponse> {
    const response = await fetch(`${this.baseUrl}/axpep/tasks/${taskId}`);
    return response.json();
  }

  async downloadResults(taskId: string, type: 'classification' | 'score' | 'result'): Promise<Blob> {
    const response = await fetch(`${this.baseUrl}/axpep/tasks/${taskId}/${type}/download`);
    return response.blob();
  }
}
```

### Example 8: Python Integration

```python
import requests
import json

class AxPEPClient:
    def __init__(self, base_url="http://api.example.com/api/v1"):
        self.base_url = base_url

    def create_ampep_task(self, email, fasta, methods, description=None):
        """Create AmPEP analysis task with text input"""
        url = f"{self.base_url}/ampep/tasks/textarea"
        data = {
            "email": email,
            "fasta": fasta,
            "methods": methods,
            "description": description
        }
        
        response = requests.post(url, json=data)
        return response.json()

    def get_task(self, task_id):
        """Get task details and results"""
        url = f"{self.base_url}/axpep/tasks/{task_id}"
        response = requests.get(url)
        return response.json()

    def download_results(self, task_id, result_type="classification"):
        """Download task results"""
        url = f"{self.base_url}/axpep/tasks/{task_id}/{result_type}/download"
        response = requests.get(url)
        return response.content

# Usage example
client = AxPEPClient()

# Create task
task_response = client.create_ampep_task(
    email="researcher@university.edu",
    fasta=">seq1\nMKLLILLLCLAFLPLVLG\n>seq2\nAKLLILLLCLAFLPLVLG",
    methods={"ampep": True, "deepampep30": True},
    description="Test analysis"
)

task_id = task_response["data"]["id"]

# Check task status
status = client.get_task(task_id)
print(f"Task status: {status['data']['action']}")

# Download results when finished
if status["data"]["action"] == "finished":
    results = client.download_results(task_id, "classification")
    with open("results.csv", "wb") as f:
        f.write(results)
```

---

This documentation provides comprehensive coverage of the AxPEP_Backend API system. For additional support or questions, please refer to the source code or contact the development team.