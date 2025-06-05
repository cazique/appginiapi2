
# API para AppGini 25.13 (PHP 8+)

Esta API te permite acceder de forma segura a tus tablas AppGini mediante métodos RESTful (GET, POST, PUT, DELETE), con autenticación integrada en AppGini.

## 📂 Estructura

Coloca esta carpeta en:

```
/app.hogarfamiliar.es/api/
```

## 🔐 Autenticación

La API usa:

- Sesión AppGini activa (si estás logueado)
- o HTTP Basic Auth con credenciales de AppGini

Ejemplo de cabecera:

```
Authorization: Basic YWRtaW46dHVjb250cmFzZW5h
```

(Usuario: `admin`, Contraseña: `tucontraseña`, codificado en base64)

## 📥 Solicitudes API

### 📄 GET (leer registros)

```
GET /api/index.php?table=clientes
```

Devuelve hasta 100 registros de la tabla `clientes`.

## 🚫 Seguridad

- Requiere HTTPS.
- Valida permisos usando `getMemberInfo()` y `check_table_permission()`.
- Permite integrar hook en `hooks/__global.php`:

```php
function before_api_call($method, $table, $memberInfo) {
    if($memberInfo['group'] == 'suspendidos') {
        return ['error' => 'Acceso denegado para este grupo'];
    }
    return true;
}
```
