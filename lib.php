<?php
/*
  Biblioteca de Funções.
    Você pode separar funções muito utilizadas nesta biblioteca, evitando replicação de código.
*/

// Verificação se a extenção dos arquivos contendo os dados do sistema legado são do tipo CSV
function checkExtension($csvPath) {
  if (pathinfo($csvPath, PATHINFO_EXTENSION) !== 'csv') {
    die("Erro: Os arquivos devem possuir a extensão .csv\n\n");
  } else {
    echo "Extensão do arquivo: ". $csvPath . " válido" . "\n\n";
  }
}

//Converte os dados da tabela CSV de agendamentos para o padrão utf-8 (somente se necessário)
function convertDataEncoding(&$csvFileData) {
  //Converte dados para utf-8
  $conversao = mb_convert_encoding($csvFileData, "UTF-8", "ISO-8859-1");
  
  if ($conversao !== false) {
    $csvFileData = $conversao;
  } else {
    echo "Não foi possível converter os dados da tabela CSV de agendamentos para o padrão UTF-8.";
  }
}

//Trata e cadastra os dados de agendamento da tabela CSV do sistema legado no banco de dados

function agendamentosMigration($agendamentosCsvData, $database) {
  
  //Verifica se há falhas ao ler o arquivo CSV contendo os dados do sistema legado
  if ($agendamentosCsvData === false) {
    die("Erro ao ler o arquivo CSV de agendamentos \n\n");
  } else {
    echo "Arquivo CSV de agendamentos aberto com sucesso! \n\n";

    //Define o código de caracteres do banco de dados como UTF-8
    mysqli_set_charset($database, "utf8");

    //SQL para inserção dos dados
    $sqlInsert = "INSERT INTO agendamentos (id_paciente, id_profissional, dh_inicio, dh_fim, id_convenio, id_procedimento, observacoes) VALUES(
      (SELECT id FROM pacientes WHERE nome LIKE ?),
      (SELECT id FROM profissionais WHERE nome LIKE ?),
      ?,
      ?,
      (SELECT id FROM convenios WHERE nome LIKE ?),
      (SELECT id FROM procedimentos WHERE nome LIKE ?),
      ?)";
    
    //SQL para verificar a existência de agendamentos duplicados
    $sqlCheckExistence = "SELECT 1 FROM agendamentos WHERE id_paciente = 
    (SELECT id FROM pacientes WHERE nome LIKE ?) 
    AND id_profissional = (SELECT id FROM profissionais WHERE nome LIKE ?) 
    AND dh_inicio = ? 
    AND dh_fim = ? 
    AND id_convenio = (SELECT id FROM convenios WHERE nome LIKE ?) 
    AND id_procedimento = (SELECT id FROM procedimentos WHERE nome LIKE ?) 
    AND observacoes = ?";

    //Preparação das declarações SQL
    $stmtInsert = mysqli_prepare($database, $sqlInsert);
    $stmtCheckExistence = mysqli_prepare($database, $sqlCheckExistence);

    //Percorre todas as linhas e colunas da tabela CSV
    while ($linhaTabelaAgendamentosFile = fgetcsv($agendamentosCsvData, 0, ",")) {

      $linhaTabelaAgendamentosFile = array_map('stripslashes', $linhaTabelaAgendamentosFile);
      $linhaTabelaAgendamentosFile = array_map('trim', $linhaTabelaAgendamentosFile);

      //Converte a coluna cod_agendamento da tabela para o tipo Inteiro
      $cod_agendamento = intval($linhaTabelaAgendamentosFile[0]);

      //Descrição do agendamento
      $descricao = $linhaTabelaAgendamentosFile[1];

      //Data e hora de início da consulta
      $dh_inicio = date_create_from_format('d/m/Y', $linhaTabelaAgendamentosFile[2])->format('Y-m-d') . ' ' . $linhaTabelaAgendamentosFile[3];

      //Data e hora do fim da consulta
      $dh_fim = date_create_from_format('d/m/Y', $linhaTabelaAgendamentosFile[2])->format('Y-m-d') . ' ' . $linhaTabelaAgendamentosFile[4];

      //Coluna de profissionais
      $profissionais = '%' . $linhaTabelaAgendamentosFile[8] . '%';

      //Coluna de convenios
      $convenio = '%' . $linhaTabelaAgendamentosFile[10] . '%';

      //Coluna de procedimentos
      $procedimentos = '%' . $linhaTabelaAgendamentosFile[11] . '%';

      //Coluna de pacientes
      $paciente = '%' . $linhaTabelaAgendamentosFile[6] . '%';

      // Verifica se já existe um registro com os mesmos dados na tabela
      mysqli_stmt_bind_param($stmtCheckExistence, "sssssss", $paciente, $profissionais, $dh_inicio, $dh_fim, $convenio, $procedimentos, $descricao);
      mysqli_stmt_execute($stmtCheckExistence);
      mysqli_stmt_store_result($stmtCheckExistence);
      $num_rows = mysqli_stmt_num_rows($stmtCheckExistence);

      if ($num_rows === 0) {

        // Se não existe, insere o novo registro
        mysqli_stmt_bind_param($stmtInsert, "sssssss", $paciente, $profissionais, $dh_inicio, $dh_fim, $convenio, $procedimentos, $descricao);

        if (!mysqli_stmt_execute($stmtInsert)) {

          die("Falha ao executar a declaração SQL: " . mysqli_stmt_error($stmtInsert));

        }
      } else {
        echo "Já existe um registro com os mesmos dados. Ignorando inserção.\n";
      }
    }

    // Fechar as declarações
    echo("Declarações SQL executadas com sucesso\n\n");
    mysqli_stmt_close($stmtInsert);
    mysqli_stmt_close($stmtCheckExistence);

  }
}


//Trata e cadastra os dados dos pacientes da tabela CSV do sistema legado no banco de dados
function pacientesMigration($pacientesCsvData, $database) {

  //Verifica se há falhas ao ler o arquivo CSV contendo os dados do sistema legado
  if ($pacientesCsvData === false) {

    die("Erro ao ler o arquivo CSV de pacientes \n\n");

  } else {
    echo "Arquivo CSV de pacientes aberto com sucesso! \n\n";

    //Define o código de caracteres do banco de dados como UTF-8
    mysqli_set_charset($database, "utf8");

    //SQL para inserção dos dados
    $sql = "INSERT INTO pacientes (nome, sexo, nascimento, cpf, rg, id_convenio, cod_referencia) VALUES (
      ?,
      ?,
      ?,
      ?,
      ?,
      (SELECT id FROM convenios WHERE nome LIKE ?),
      ?
    )";

    //SQL para verificar se há pacientes repetidos pelo CPF
    $sqlCheck = "SELECT COUNT(*) FROM pacientes WHERE cpf LIKE ?";
    $stmtCheck = mysqli_prepare($database, $sqlCheck);

    //Preparação da declaração SQL
    $stmt = mysqli_prepare($database, $sql);

    //Percorre todas as linhas e colunas da tabela CSV
    while ($linhaTabelaPacientesFile = fgetcsv($pacientesCsvData, 0, ';')) {

      // array_walk_recursive($linhaTabelaPacientesFile, 'convertDataEncoding');

      $linhaTabelaPacientesFile = array_map('stripslashes', $linhaTabelaPacientesFile);
      $linhaTabelaPacientesFile = array_map('trim', $linhaTabelaPacientesFile);

      //Código referência
      $cod_referencia = intval($linhaTabelaPacientesFile[0]);

      //Coluna de nome
      $nome_paciente = $linhaTabelaPacientesFile[1]; 

      //Coluna de data de nascimento
      $nasc_paciente = date_create_from_format('d/m/Y', $linhaTabelaPacientesFile[2])->format('Y-m-d'); 

      //coluna de CPF
      $cpf_paciente = $linhaTabelaPacientesFile[5]; 

      //coluna de RG
      $rg_paciente = $linhaTabelaPacientesFile[6]; 

      //Coluna de sexo 
      $sexo_pac = $linhaTabelaPacientesFile[7] == 'M' ? 'Masculino' : 'Feminino'; 

      //Coluna de código do convenio
      $id_conv = '%' . $linhaTabelaPacientesFile[9] . '%';
      
      //Coluna com o nome do convenio
      $convenioName = $linhaTabelaPacientesFile[9];

      //Verificar se o paciente já existe
      mysqli_stmt_bind_param($stmtCheck, "s", $cpf_paciente);
      mysqli_stmt_execute($stmtCheck);
      mysqli_stmt_store_result($stmtCheck);
      mysqli_stmt_bind_result($stmtCheck, $count);
      mysqli_stmt_fetch($stmtCheck);


      if ($count == 0) {
        // Verificar se o convênio já existe na tabela convenios
        $checkConvenioQuery = "SELECT COUNT(*) FROM convenios WHERE nome LIKE ?";
        $stmtCheckConvenio = mysqli_prepare($database, $checkConvenioQuery);
        mysqli_stmt_bind_param($stmtCheckConvenio, "s", $convenioName);
        mysqli_stmt_execute($stmtCheckConvenio);
        mysqli_stmt_store_result($stmtCheckConvenio);
        mysqli_stmt_bind_result($stmtCheckConvenio, $convenioCount);
        mysqli_stmt_fetch($stmtCheckConvenio);
        mysqli_stmt_close($stmtCheckConvenio);

        if ($convenioCount == 0) {
          // Se o convênio não existe, inserir na tabela convenios
          $insertConvenioQuery = "INSERT INTO convenios (nome) VALUES (?)";
          $stmtInsertConvenio = mysqli_prepare($database, $insertConvenioQuery);
          mysqli_stmt_bind_param($stmtInsertConvenio, "s", $convenioName);
          mysqli_stmt_execute($stmtInsertConvenio);
          mysqli_stmt_close($stmtInsertConvenio);
        }

        // Inserir o paciente na tabela pacientes
        mysqli_stmt_bind_param($stmt, 'ssssssi', $nome_paciente, $sexo_pac, $nasc_paciente, $cpf_paciente, $rg_paciente, $id_conv, $cod_referencia);

        if (!mysqli_stmt_execute($stmt)) {

          die("Falha ao executar a declaração SQL: " . mysqli_stmt_error($stmt));
          
        }
      } else {
        echo "Paciente com o CPF '$cpf_paciente' já existe. Ignorando inserção.\n";
      }
    }

    // Fechar a declaração
    echo("Declaração SQL executada com sucesso\n\n");
    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmtCheck);

  }
}

function dateNow(){
  date_default_timezone_set('America/Sao_Paulo');
  return date('d-m-Y \à\s H:i:s');
}