# Doctro API

### 1) Architecture layers
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

### 2) User login
**URL:** http://(awesome-api-here)/api/login

**METHOD:** POST

**BODY:** (Content-Type: application/json) (raw json)
```json
{
    "username": "email@doctro.tld",
    "password": "plain password here"
}
```
**RESPONSE**:
```json
{
    "user": "email@doctro.tld",
    "token": "super secret token here"
}
```
###### *WORK IN PROGRESS: token generator & token handler
