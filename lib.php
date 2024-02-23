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
      $sql = "INSERT INTO agendamentos (id_paciente, id_profissional, dh_inicio, dh_fim, id_convenio, id_procedimento, observacoes) VALUES(
        (SELECT id FROM pacientes WHERE nome LIKE ?),
        (SELECT id FROM profissionais WHERE nome LIKE ?),
        ?,
        ?,
        (SELECT id FROM convenios WHERE nome LIKE ?),
        (SELECT id FROM procedimentos WHERE nome LIKE ?),
        ?)";

      //Preparação da declaração SQL
      $stmt = mysqli_prepare($database, $sql);

      //Percorre todas as linhas e colunas da tabela CSV
      while ($linhaTabelaAgendamentosFile = fgetcsv($agendamentosCsvData, 0, ",")) {

        $linhaTabelaAgendamentosFile = array_map('stripslashes', $linhaTabelaAgendamentosFile);
        $linhaTabelaAgendamentosFile = array_map('trim', $linhaTabelaAgendamentosFile);

        //Converte a coluna cod_agendamento da tabela para o tipo Inteiro
        $cod_agendamento = intval($linhaTabelaAgendamentosFile[0]);

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

        //Vincular os parâmetros da consulta
        mysqli_stmt_bind_param($stmt, "sssssss", $paciente, $profissionais, $dh_inicio, $dh_fim, $convenio, $procedimentos, $descricao);

        if (!mysqli_stmt_execute($stmt)) {
          die("Falha ao executar a declaração SQL: " . mysqli_stmt_error($stmt));
        }
      }
      // Fechar a declaração
      mysqli_stmt_close($stmt);
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
    $sql = "INSERT INTO pacientes (nome, sexo, nascimento, cpf, rg, id_convenio) VALUES (
      ?,
      ?,
      ?,
      ?,
      ?,
      (SELECT id FROM convenios WHERE nome LIKE ?)
    )";

    //Preparação da declaração SQL
    $stmt = mysqli_prepare($database, $sql);

    //Percorre todas as linhas e colunas da tabela CSV
    while ($linhaTabelaPacientesFile = fgetcsv($pacientesCsvData, 0, ';')) {

      // array_walk_recursive($linhaTabelaPacientesFile, 'convertDataEncoding');

      $linhaTabelaPacientesFile = array_map('stripslashes', $linhaTabelaPacientesFile);
      $linhaTabelaPacientesFile = array_map('trim', $linhaTabelaPacientesFile);

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

      //Vincular os parâmetros da consulta
      mysqli_stmt_bind_param($stmt, 'ssssss', $nome_paciente, $sexo_pac, $nasc_paciente, $cpf_paciente, $rg_paciente, $id_conv);

      if (!mysqli_stmt_execute($stmt)) {
        die("Falha ao executar a declaração SQL: " . mysqli_stmt_error($stmt));
      }
    }
    // Fechar a declaração
    mysqli_stmt_close($stmt);
  }

}

function dateNow(){
  date_default_timezone_set('America/Sao_Paulo');
  return date('d-m-Y \à\s H:i:s');
}