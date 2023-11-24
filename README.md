# Doctro API <sup>(beta)</sup>
<hr>

### Table of Contents
**[Setup & Run](#setup--run)**<br>
**[Architecture layers](#architecture-layers)**<br>
**[Register patient / doctor](#register-patient--doctor)**<br>
**[User login](#user-login-suplogin-as-patient--doctorsup)**<br>
**[User logout](#user-logout)**<br>
**[API -- list doctors](#api----list-doctors)**<br>
**[API -- list doctor details](#api----list-doctor-details)**<br>
**[API -- add appointment](#api----add-appointment)**<br>
**[API -- appointment list (TBD)](#api----appointment-list)**<br>
**[API -- appointment cancel (TBD)](#api----appointment-cancel)**<br>
**[API -- medical list (TBD)](#api----medical-list)**<br>
<hr>

## Setup & Run

#### 1) Clone repo
#### 2) Change database user/password in `doctro/.env` (line 30)
#### 3) Create database & tables
`./bin/console doctrine:database:create`<br>
`./bin/console doctrine:schema:create`
#### 4) Download & install symfony-cli
from symfony: `curl -sS https://get.symfony.com/cli/installer | bash` <br>
or using Homebrew: `brew install symfony-cli/tap/symfony-cli`
#### 5) Run symfony server
`symfony server:start`

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

## Register patient / doctor
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

## User login <sup>(login as patient / doctor)</sup>
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

## User logout
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

## API -- list doctors
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

## API -- list doctor details
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

## API -- add appointment
[POST] http://(awesome-api-here)/api/appointment

**Request Header:**
```json
{
    "Authorization": "Bearer super-secret-token"
}
```
**Request BODY:** (Content-Type: application/json) (raw json)
```json
{
    "doctorId": 2,
    "specialtyId": 1,
    "hospitalId": 1,
    "hospitalServiceId": 2,
    "date": "2023-11-23"
}
```
**RESPONSE:**
```json
{
    "is_error": false,
    "code": 200,
    "errors": [],
    "data": {
        "appointment_id": 1
    }
}
```
<hr>

## API -- appointment list
[GET] http://(awesome-api-here)/api/appointments

**Request Header:**
```json
{
    "Authorization": "Bearer super-secret-token"
}
```
**Request QUERY:**
```json
{
    // "hospitalId": 1|2,
    // "specialtyId": 1|2,
    // "doctorName": "what's up doc",
    // "date": "2023-11-23|24|25",
    // "status": "TBD"
}
```
###### *status filter not working -- TBD
**RESPONSE:**
```json
{
    "is_error": false,
    "code": 200,
    "errors": [],
    "data": [
        {
            "appointment_id": 1,
            "patient_id": 1,
            "patient_name": "Pacient Unu",
            "doctor_id": 2,
            "doctor_name": "Doctor 1",
            "specialty_id": 1,
            "specialty_name": "Medicina Generala",
            "hospital_service_id": 1,
            "hospital_service_name": "Consult General",
            "hospital_id": 1,
            "hospital_name": "Colentina",
            "appointment_date": "2023-11-23T00:00:00+02:00"
        },
        {
            "appointment_id": 2,
            "patient_id": 1,
            "patient_name": "Pacient Unu",
            "doctor_id": 3,
            "doctor_name": "Doctor 2",
            "specialty_id": 2,
            "specialty_name": "Specialitate",
            "hospital_service_id": 4,
            "hospital_service_name": "Spec. Consult",
            "hospital_id": 2,
            "hospital_name": "Ilfov",
            "appointment_date": "2023-11-24T00:00:00+02:00"
        },
        {
            "appointment_id": 3,
            "patient_id": 1,
            "patient_name": "Pacient Unu",
            "doctor_id": 4,
            "doctor_name": "Doctor 3",
            "specialty_id": 2,
            "specialty_name": "Specialitate",
            "hospital_service_id": 4,
            "hospital_service_name": "Spec. Consult",
            "hospital_id": 2,
            "hospital_name": "Ilfov",
            "appointment_date": "2023-11-25T00:00:00+02:00"
        }
    ]
}
```
<hr>

## API -- appointment cancel
TBD
<hr>

## API -- medical list
TBD
