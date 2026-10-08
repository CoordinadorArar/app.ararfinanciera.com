<?php

namespace Tests\Unit;

use App\Services\ValidadorTercero;
use PHPUnit\Framework\TestCase;

class ValidadorTerceroTest extends TestCase
{
    const HOY = '2026-10-07';

    public function test_identifica_tipo_de_documento_por_nombre()
    {
        $this->assertSame('CC', ValidadorTercero::tipoDocumento('Cédula de ciudadanía'));
        $this->assertSame('CE', ValidadorTercero::tipoDocumento('Cédula de extranjería'));
        $this->assertSame('NIT', ValidadorTercero::tipoDocumento('NIT'));
        $this->assertSame('PA', ValidadorTercero::tipoDocumento('Pasaporte'));
        $this->assertSame('CC', ValidadorTercero::tipoDocumento('C.C.'));
        $this->assertNull(ValidadorTercero::tipoDocumento('Tarjeta de identidad'));
        $this->assertNull(ValidadorTercero::tipoDocumento(null));
    }

    public function test_cedula_de_ciudadania_numerica_de_5_a_10_digitos()
    {
        $this->assertNull(ValidadorTercero::documento('CC', '12345'));
        $this->assertNull(ValidadorTercero::documento('CC', '1095833971'));
        $this->assertNotNull(ValidadorTercero::documento('CC', '1234'));
        $this->assertNotNull(ValidadorTercero::documento('CC', '12345678901'));
        $this->assertNotNull(ValidadorTercero::documento('CC', '12A456'));
        $this->assertNotNull(ValidadorTercero::documento('CC', ''));
    }

    public function test_cedula_de_extranjeria()
    {
        $this->assertNull(ValidadorTercero::documento('CE', '123'));
        $this->assertNotNull(ValidadorTercero::documento('CE', '12'));
        $this->assertNotNull(ValidadorTercero::documento('CE', 'E12345'));
    }

    public function test_nit_con_digito_de_verificacion_opcional()
    {
        $this->assertNull(ValidadorTercero::documento('NIT', '800197268'));
        $this->assertNull(ValidadorTercero::documento('NIT', '800197268-4'));
        $this->assertSame('El dígito de verificación del NIT no es válido.', ValidadorTercero::documento('NIT', '800197268-5'));
        $this->assertNotNull(ValidadorTercero::documento('NIT', '80019726845'));
        $this->assertNotNull(ValidadorTercero::documento('NIT', '800.197.268'));
        $this->assertSame(4, ValidadorTercero::digitoVerificacion('800197268'));
        $this->assertSame('800197268', ValidadorTercero::baseDocumento('800197268-4'));
    }

    public function test_pasaporte_numerico_de_5_a_10_digitos()
    {
        $this->assertNull(ValidadorTercero::documento('PA', '12345'));
        $this->assertNull(ValidadorTercero::documento('PA', '1234567890'));
        $this->assertSame('El pasaporte debe tener entre 5 y 10 dígitos.', ValidadorTercero::documento('PA', '1234'));
        $this->assertNotNull(ValidadorTercero::documento('PA', '12345678901'));
        $this->assertNotNull(ValidadorTercero::documento('PA', 'AB12345'));
    }

    public function test_tipo_desconocido_usa_regla_generica()
    {
        $this->assertNull(ValidadorTercero::documento(null, '1234567'));
        $this->assertNotNull(ValidadorTercero::documento(null, '12'));
    }

    public function test_documento_almacenable_en_columna_int()
    {
        $this->assertTrue(ValidadorTercero::documentoAlmacenable('2147483647'));
        $this->assertTrue(ValidadorTercero::documentoAlmacenable('800197268-4'));
        $this->assertFalse(ValidadorTercero::documentoAlmacenable('2147483648'));
        $this->assertFalse(ValidadorTercero::documentoAlmacenable('AB12345'));
        $this->assertFalse(ValidadorTercero::documentoAlmacenable(''));
    }

    public function test_fecha_de_nacimiento_entre_18_y_99_anos()
    {
        $this->assertNull(ValidadorTercero::fechaNacimiento('2008-10-07', self::HOY));
        $this->assertNull(ValidadorTercero::fechaNacimiento('1926-10-08', self::HOY));
        $this->assertNull(ValidadorTercero::fechaNacimiento('1990/05/20', self::HOY));
        $this->assertSame('El titular debe ser mayor de 18 años.', ValidadorTercero::fechaNacimiento('2008-10-08', self::HOY));
        $this->assertSame('El titular debe ser menor de 100 años.', ValidadorTercero::fechaNacimiento('1926-10-07', self::HOY));
        $this->assertSame('La fecha de nacimiento no puede ser futura.', ValidadorTercero::fechaNacimiento('2027-01-01', self::HOY));
        $this->assertSame('La fecha de nacimiento no es válida.', ValidadorTercero::fechaNacimiento('2001-02-30', self::HOY));
        $this->assertSame('La fecha de nacimiento no es válida.', ValidadorTercero::fechaNacimiento('abc', self::HOY));
    }

    public function test_fecha_de_expedicion_no_futura_y_tras_los_18_anos()
    {
        $this->assertNull(ValidadorTercero::fechaExpedicion('2008-05-20', '1990-05-20', self::HOY));
        $this->assertNull(ValidadorTercero::fechaExpedicion(self::HOY, '1990-05-20', self::HOY));
        $this->assertSame('La fecha de expedición debe ser posterior a la fecha en que el titular cumplió 18 años.', ValidadorTercero::fechaExpedicion('2008-05-19', '1990-05-20', self::HOY));
        $this->assertSame('La fecha de expedición no puede ser futura.', ValidadorTercero::fechaExpedicion('2026-10-08', '1990-05-20', self::HOY));
        $this->assertSame('La fecha de expedición no es válida.', ValidadorTercero::fechaExpedicion('2020-13-01', '1990-05-20', self::HOY));
        $this->assertNull(ValidadorTercero::fechaExpedicion('2020-01-01', 'invalida', self::HOY));
    }

    public function test_telefono_movil_o_fijo()
    {
        $this->assertNull(ValidadorTercero::telefono('3157096395'));
        $this->assertNull(ValidadorTercero::telefono('315 709 6395'));
        $this->assertNull(ValidadorTercero::telefono('6391010'));
        $this->assertNull(ValidadorTercero::telefono('6076391010'));
        $this->assertNull(ValidadorTercero::telefono('(607) 639-1010'));
        $this->assertNotNull(ValidadorTercero::telefono('315709639'));
        $this->assertNotNull(ValidadorTercero::telefono('31570963951'));
        $this->assertNotNull(ValidadorTercero::telefono('123456'));
        $this->assertNotNull(ValidadorTercero::telefono('0391010'));
        $this->assertNotNull(ValidadorTercero::telefono('+573157096395'));
        $this->assertNotNull(ValidadorTercero::telefono('315709A395'));
        $this->assertSame('3157096395', ValidadorTercero::limpiarTelefono(' 315-709.6395 '));
    }
}
