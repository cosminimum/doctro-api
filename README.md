# Doctro API <sup>(beta)</sup>
<hr>

## Architecture layers
<table>
    <tbody>
        <tr>
            <td>Presentation</td>
            <td>Infrastructure</td>
        </tr>
        <tr>
            <td colspan="2"><center>Application</center></td>
        </tr>
        <tr>
            <td colspan="2"><center>Domain</center></td>
        </tr>
    </tbody>
</table>

###### *very important: each layer have access only to the lower layer(s)
<hr>

### 1) Register patient / doctor
[POST] http://(awesome-api-here)/register/patient <br>
[POST] http://(awesome-api-here)/register/doctor

**Request BODY:** (Content-Type: application/json) (raw json)
```json
{
    "email": "email@gmail.com",
    "firstName": "First name",
    "lastName": "Last name",
    "cnp": "1880000000000",
    "phone": "+40743000000",
    "password": "super strong plain password here"
}
```
###### *cnp length is locked on 13 chars (min/max)
**RESPONSE:**
```json
{
    "is_error": false,
    "code": 200,
    "errors": [],
    "data": {
        "user_id": 1
    }
}
```
<hr>

### 2) User login <sup>(login as patient / doctor)</sup>
[POST] http://(awesome-api-here)/login

**Request BODY:** (Content-Type: application/json) (raw json)
```json
{
    "username": "email@domain.tld",
    "password": "plain password here"
}
```
**RESPONSE:**
```json
{
    "is_error": false,
    "code": 200,
    "errors": [],
    "data": {
        "user_name": "email@domaino.tld",
        "user_id": 1,
        "token": "super-secret-token"
    }
}
```
###### *the token validity is 1 year! -- use logout if need to force token expiration earlier
<hr>

### 3) User logout
[GET] http://(awesome-api-here)/logout

**Request Header:**
```json
{
    "Authorization": "Bearer super-secret-token"
}
```
**RESPONSE:** (redirecting on homepage)
```json
{
    "error": false,
    "code": 200,
    "errors": [],
    "data": {
        "who_am_i": "Doctro API",
        "who_you_are": "::1"
    }
}
```
<hr>

### 4) API -- list doctors
[GET] http://(awesome-api-here)/api/doctors

**Request Header:**
```json
{
    "Authorization": "Bearer super-secret-token"
}
```
**Request QUERY:**
```json
{
    "hospitalId": 1,
    "specialtyId": 2,
    "doctorName": "full or partial name",
    "serviceId": 3,
    "availableDate": "2020-01-01"
}
```
###### *availableDate filter does nothing for now
**RESPONSE:**
```json
{
    "is_error": false,
    "code": 200,
    "errors": [],
    "data": [
        {
            "id": 1,
            "email": "doctor1@domain.tld",
            "first_name": "First Name",
            "last_name": "Last Name",
            "cnp": "1880000000000",
            "phone": "+40743000000"
        },
        {
            "id": 2,
            "email": "doctor2@domain.tld",
            "first_name": "First Name",
            "last_name": "Last Name",
            "cnp": "1880000000000",
            "phone": "+40743000000"
        },
        {
            "id": 3,
            "email": "doctor3@domain.tld",
            "first_name": "First Name",
            "last_name": "Last Name",
            "cnp": "1880000000000",
            "phone": "+40743000000"
        }
    ]
}
```
<hr>

### 5) API -- list doctor details
[GET] http://(awesome-api-here)/api/doctor/1

**Request Header:**
```json
{
    "Authorization": "Bearer super-secret-token"
}
```
**RESPONSE:**
```json
{
    "is_error": false,
    "code": 200,
    "errors": [],
    "data": {
        "id": 1,
        "email": "doctor1@domain.tld",
        "first_name": "First Name",
        "last_name": "Last Name",
        "cnp": "18800200000000",
        "phone": "+40743000000",
        "specialties": [
            {
                "specialty_id": 1,
                "specialty_code": "general",
                "specialty_name": "Medicina Generala"
            },
            {
                "specialty_id": 2,
                "specialty_code": "specialitate",
                "specialty_name": "Specialitate"
            }
        ],
        "hospital_services": [
            {
                "hospital_service_id": 1,
                "hospital_service_name": "Consult General",
                "hospital_id": 1,
                "hospital_name": "Colentina",
                "medical_service_id": 1,
                "medical_service_name": "Consult General",
                "medical_service_code": "gen_cons"
            },
            {
                "hospital_service_id": 4,
                "hospital_service_name": "Consult de specialitate",
                "hospital_id": 2,
                "hospital_name": "Spital Judetean Ilfov",
                "medical_service_id": 2,
                "medical_service_name": "Consult Special",
                "medical_service_code": "spec_cons"
            }
        ]
    }
}
```
<hr>

### 6) API -- add appointment
TBD
<hr>

### 7) API -- appointment list
TBD
<hr>

### 8) API -- appointment cancel
TBD
<hr>

### 9) API -- medical list
TBD
