
document.addEventListener('DOMContentLoaded',function(){


    

    if(document.querySelector("#formAsignaOperador")){
        let formAsignaOperador = document.querySelector("#formAsignaOperador");
        formAsignaOperador.onsubmit = function(e) {
            e.preventDefault();
            let operador = document.querySelector('#operador').value;
            

            if(operador == '0')
            {
                Swal.fire("Atención", "Debe seleccionar un operador." , "error");
                return false;
            }

            
            divLoading.style.display = "flex";
            let request = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
            let ajaxUrl = base_url+'/Home/asignaOperador'; 
            let formData = new FormData(formAsignaOperador);
            request.open("POST",ajaxUrl,true);
            request.send(formData);
            request.onreadystatechange = function(){
                if(request.readyState != 4 ) return; 
                if(request.status == 200){
                    let objData = JSON.parse(request.responseText);
                    if(objData.status)
                    {

                        //Swal.fire("Exito", objData.msg , "success");
                        window.location = base_url+'/home';

                    }else{
                        Swal.fire("Error", objData.msg , "error");
                    }
                }
                divLoading.style.display = "none";
                return false;
            }
        }
    }


    if(document.querySelector("#formContar")){
        let formContar = document.querySelector("#formContar");
        formContar.onsubmit = function(e) {
            e.preventDefault();
            
            let barra = document.querySelector('#barra').value;
            let dcl_numero = document.querySelector('#dcl_numero').value;
            let dcl_tdt_codigo = document.querySelector('#dcl_tdt_codigo').value;
            

            if(barra.isEmpty)
            {
                Swal.fire("Atención", "No se envio código de barra" , "error");
                return false;
            }

            
            divLoading.style.display = "flex";
            let request = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
            let ajaxUrl = base_url+'/Home/setConteo'; 
            let formData = new FormData(formContar);
            request.open("POST",ajaxUrl,true);
            request.send(formData);
            request.onreadystatechange = function(){
                if(request.readyState != 4 ) return; 
                if(request.status == 200){
                    let objData = JSON.parse(request.responseText);
                    if(objData.status)
                    {
                        location. reload()
                        //Swal.fire("Exito", objData.msg , "success");
                    }else{
                        Swal.fire("Error", objData.msg , "error");
                    }
                }
                divLoading.style.display = "none";
                return false;
            }
        }
    }


},false);


function fntEditOrden(idOrden){

   $('#modalFormOperador').modal('show');
   document.querySelector("#doc").value = idOrden; 

}


function viewCubeta(idcubeta){

    let request = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
        let ajaxUrl = base_url+'/Home/getCubeta';
        let strData = "id="+idcubeta;
        request.open("POST",ajaxUrl,true);
        request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        request.send(strData);
        request.onreadystatechange = function(){
            if(request.readyState == 4 && request.status == 200){
                let objData = JSON.parse(request.responseText);
                if(objData.status)
                {
                    document.querySelector('#codigoCub').innerHTML = objData.data.CUB_CODIGO;                    
                    document.querySelector('#descripcionCub').innerHTML = objData.data.CUB_DESCRIPCION;                    
                    document.querySelector('#fechaCub').innerHTML = objData.data.CUB_FECHAHORA;                    
                    document.querySelector('#contenidoCub').innerHTML = objData.data.CUB_CONTENIDO;                    


                }else{
                    Swal.fire("Atención!", objData.msg , "error");
                }
            }
        }

   $('#modalViewCubeta').modal('show');
}


function finalizarConteo(dcl_id){

    const swalWithBootstrapButtons = Swal.mixin({
      customClass: {
        confirmButton: 'btn btn-success',
        cancelButton: 'btn btn-danger'
    },
    buttonsStyling: false
})

    swalWithBootstrapButtons.fire({
      title: 'Estas Seguro/a?',
      text: "No podras revertir este cambio!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Si, Continuar',
      cancelButtonText: 'No, cancelar!',
      reverseButtons: true
  }).then((result) => {
      if (result.isConfirmed) {

        //peticion ajax 
        divLoading.style.display = "flex";
        let request = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
        let ajaxUrl = base_url+'/Home/finalizaCubetaConteo';
        let strData = "dcl_id="+dcl_id;
        request.open("POST",ajaxUrl,true);
        request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        request.send(strData);
        request.onreadystatechange = function(){
            if(request.readyState == 4 && request.status == 200){
                let objData = JSON.parse(request.responseText);
                if(objData.status)
                {
                    Swal.fire("Atención!", objData.msg , "success");

                    location. reload()
                }else{
                    Swal.fire("Atención!", objData.msg , "error");
                }
                divLoading.style.display = "none";
            }
        }

    } else if (

        result.dismiss === Swal.DismissReason.cancel
        ) {
        swalWithBootstrapButtons.fire(
          'Cancelado',
          'conteo no se registro :)',
          'error'
          )
    }
})

}


function cubetaLLena(dcl_id){

    Swal.fire({
        title: 'Descripción cubeta',
      input: 'text',
      inputAttributes: {
        autocapitalize: 'off'
    },
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    showLoaderOnConfirm: true,
    preConfirm: (descripcion) => {
        
        let request = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
        let ajaxUrl = base_url+'/Home/finalizaCubetaConteo';
        let strData = "descripcion="+descripcion+"&dcl_id="+dcl_id;
        request.open("POST",ajaxUrl,true);
        request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        request.send(strData);
        request.onreadystatechange = function(){
            if(request.readyState == 4 && request.status == 200){
                let objData = JSON.parse(request.responseText);
                if(objData.status)
                {
                    //Swal.fire("Atención!", objData.msg , "success");

                    location. reload()
                }else{
                    Swal.fire("Atención!", objData.msg , "error");
                }
                divLoading.style.display = "none";
            }
        }

    },
    allowOutsideClick: () => !Swal.isLoading()
}).then((result) => {
  if (result.isConfirmed) {

    Swal.fire("Cubeta Creada", '' , "success");
}
})

}