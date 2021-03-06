<?php

namespace App\Http\Controllers;

use App\Helpers\JwtAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

use App\Models\User;
//use Facade\FlareClient\Http\Response;

class UserController extends Controller
{
    //
    public function pruebas() {
        return "Acción de pruebas de USER-CONTROLLER";
    }

    public function register(Request $request) {

        //Recoger los datos del usuario por POST
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if(!empty($params) && !empty($params_array)) {
            //Limpiar datos
            $params_array = array_map('trim', $params_array);

            //Validar datos
            $validate = Validator::make($params_array, [
                'nombre'    => 'required|alpha',
                'apellidos' => 'required|string',
                'email'     => 'required|email|unique:user',
                'password'  => 'required'
            ]);

            if($validate->fails()) {
                $data = array (
                    'status' => 'error',
                    'code'   => '404',
                    'message' => 'El usuario no se ha creado',
                    'errors'  => $validate->errors()
                );
            } else {

                //Cifrar la contraseña
                $pwd = hash('sha256',$params->password);

                //Crear el usuario
                $user = new User();
                $user->nombre    =   $params_array['nombre'];
                $user->apellidos =   $params_array['apellidos'];
                $user->email     =   $params_array['email'];
                $user->password  =   $pwd;

                $user->save();

                $data = array (
                    'status' => 'success',
                    'code'   => '200',
                    'message' => 'El usuario se ha creado',
                );
            }
        } else {
            $data = array(
                'status' => 'success',
                'code'   => '400',
                'message' => 'Error'
            );
        }

        return response()->json($data, $data['code']);
    }

    public function login(Request $request) {

        $jwtAuth = new \JwtAuth();

        //Recibir datos por POST
        $json = $request->input('json'. null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        //Validar esos datos
        $validate = Validator::make($params_array, [
            'email'     => 'required|email',
            'password'  => 'required'
        ]);

        if($validate->fails()) {
            $data = array (
                'status' => 'error',
                'code'   => '404',
                'message' => 'El usuario no se ha logueado correctamente',
                'errors'  => $validate->errors()
            );
        } else {

            //Cifrar password
            $pwd = hash('sha256',$params->password);

            //Devolver token o datos
            $singup = $jwtAuth->singup($params->email, $pwd);

            if(!empty($params->getTooken)) {
                $singup = $jwtAuth->singup($params->email, $pwd, true);
            }
        }

        return response()->json($singup, 200);
    }

    public function update(Request $request) {

        //Comprobar si el usuario esta identificado
        $token = $request->header('Authorization');
        $jwtAuth = new \JwtAuth();

        $checkToken = $jwtAuth->checkToken($token);

        //Actualizar el usuario
        //Recoger datos por POST
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        if($checkToken && !empty($params_array)) {
            //Recoger id de usuario
            $user = $jwtAuth->checkToken($token, true);

            //Validar los datos
            $validate = Validator::make($params_array, [
                'nombre'    => 'required|alpha',
                'apellidos' => 'required|string',
                'email'     => 'required|email|unique:user,'.$user->sub
            ]);

            //Quitar los campos que no quiero actualizar
            unset($params_array['id']);
            unset($params_array['role']);
            unset($params_array['password']);
            unset($params_array['created_at']);
            unset($params_array['remember_token']);

            //Actualizar el usuario en la BD
            $user_update = User::where('id', $user->sub)->update($params_array);

            //Devolver array con el resultado
            $data = array(
                'code'      => 200,
                'status'    => 'success',
                'user'      => $user_update
            );


        } else {
            $data = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'El usuario no esta identidicado'
            );
        }

        return response()->json($data, $data['code']);
    }

    public function upload(Request $request) {
        //Recoger datos de la petición
        $image = $request->file('file0');

        //Validar imagen
        $validate = Validator::make($request->all(), [
            'file0' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        //Guardar imagen
        if(!$image  || $validate->fails()) {
            $data = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error al subir imagen'
            );
        } else {
            $image_name = time().$image->getClientOriginalName();
            \Storage::disk('users')->put($image_name, \File::get($image));

            $data = array(
                'code'      => 200,
                'status'    => 'success',
                'image'     => $image_name
            );
        }

        return response()->json($data, $data['code']);
    }

    public function getImage($filename) {
        $isset = \Storage::disk('users')->exists($filename);
        if($isset) {
            $file = \Storage::disk('users')->get($filename);
            return new Response($file, 200);
        } else {
            $data = array(
                'code'      => 404,
                'status'    => 'error',
                'message'     => 'La imagen no existe'
            );

        }

        return response()->json($data, $data['code']);
    }

    public function profile($id) {
        $user = User::find($id);

        if(is_object($user)) {
            $data = array(
                'code'      => 200,
                'status'    => 'success',
                'user'      => $user
            );
        } else {
            $data = array(
                'code'      => 404,
                'status'    => 'error',
                'message'   => 'El usuario no existe'
            );
        }

        return response()->json($data, $data['code']);
    }
}
